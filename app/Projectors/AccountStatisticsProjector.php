<?php

namespace App\Projectors;

use App\Enums\AccountAllocationComponentEnum;
use App\Events\Credits\CreditNoteAllocated;
use App\Events\Credits\CreditNoteIssued;
use App\Events\Invoices\InvoiceCreated;
use App\Events\Invoices\LateChargeApplied;
use App\Events\Payments\OverpaymentAllocated;
use App\Events\Payments\OverpaymentCreated;
use App\Events\Payments\PaymentAllocated;
use App\Events\Payments\PaymentReceived;
use App\Events\Refunds\OverpaymentRefunded;
use App\Events\Refunds\PaymentAllocationReversed;
use App\Events\Refunds\RefundIssued;
use App\Models\AccountInvoice;
use App\Models\AccountStatistics;
use Illuminate\Support\Carbon;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class AccountStatisticsProjector extends Projector
{
    public function onInvoiceCreated(InvoiceCreated $event)
    {
        $stats = $this->getStats($event->accountId);
        
        $stats->invoices_count++;
        $stats->total_principal_billed_amt += $event->amount;
        $stats->remaining_balance_amt += $event->amount;
        $stats->remaining_principal_amt += $event->amount;
        $stats->last_invoice_at = Carbon::parse($event->occuredAt);
        $stats->last_event_at = Carbon::parse($event->occuredAt);
        
        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onLateChargeApplied(LateChargeApplied $event)
    {
        $stats = $this->getStats($event->accountId);
        
        $stats->total_late_charge_billed_amt += $event->amount;
        $stats->remaining_balance_amt += $event->amount;
        $stats->remaining_late_charge_amt += $event->amount;
        $stats->last_event_at = Carbon::parse($event->occuredAt);
        
        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onPaymentReceived(PaymentReceived $event)
    {
        $stats = $this->getStats($event->accountId);
        
        $stats->total_payments_amt += $event->amount;
        // PaymentReceived doesn't immediately reduce balance if not allocated? 
        // Actually, balance should reflect total debt - total payments.
        $stats->remaining_balance_amt -= $event->amount;
        $stats->last_payment_at = Carbon::parse($event->occuredAt);
        $stats->last_event_at = Carbon::parse($event->occuredAt);
        
        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onRefundIssued(RefundIssued $event)
    {
        $stats = $this->getStats($event->accountId);
        
        $stats->total_refunded_amt += $event->amount;
        $stats->last_event_at = Carbon::parse($event->occuredAt);
        
        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onCreditNoteIssued(CreditNoteIssued $event)
    {
        $stats = $this->getStats($event->accountId);
        
        $stats->total_credits_amt += $event->amount;
        $stats->remaining_balance_amt -= $event->amount;
        $stats->last_event_at = Carbon::parse($event->occuredAt);
        
        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onPaymentAllocated(PaymentAllocated $event)
    {
        $stats = $this->getStats($event->accountId);
        $stats->total_allocated_payments_amt += $event->amount;
        if($event->component === AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value){
            $stats->total_allocated_principal_amt += $event->amount;
            $stats->remaining_principal_amt -= $event->amount;
        }else if($event->component === AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value){
            $stats->total_allocated_late_charge_amt += $event->amount;
            $stats->remaining_late_charge_amt -= $event->amount;
        }
        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onOverpaymentAllocated(OverpaymentAllocated $event)
    {
        $stats = $this->getStats($event->accountId);
        $stats->total_allocated_payments_amt += $event->amount;
        $stats->unallocated_overpayment_amt -= $event->amount;
        if($event->component === AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value){
            $stats->total_allocated_principal_amt += $event->amount;
            $stats->remaining_principal_amt -= $event->amount;
        }else if($event->component === AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value){
            $stats->total_allocated_late_charge_amt += $event->amount;
            $stats->remaining_late_charge_amt -= $event->amount;
        }
        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onCreditNoteAllocated(CreditNoteAllocated $event)
    {
        $stats = $this->getStats($event->accountId);
        $stats->total_allocated_credits_amt += $event->amount;
        $stats->save();
    }

    public function onPaymentAllocationReversed(PaymentAllocationReversed $event)
    {
        $stats = $this->getStats($event->accountId);
        $stats->total_allocated_payments_amt -= $event->amount;
        $stats->remaining_balance_amt += $event->amount; // Reversing payment increases balance
        if($event->component === AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value){
            $stats->total_allocated_principal_amt -= $event->amount;
            $stats->remaining_principal_amt += $event->amount;
        }else if($event->component === AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value){
            $stats->total_allocated_late_charge_amt -= $event->amount;
            $stats->remaining_late_charge_amt += $event->amount;
        }
        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onOverpaymentCreated(OverpaymentCreated $event)
    {
        $stats = $this->getStats($event->accountId);
        $stats->unallocated_overpayment_amt += $event->amount;
        $stats->save();
    }

    public function onOverpaymentRefunded(OverpaymentRefunded $event)
    {
        $stats = $this->getStats($event->accountId);
        $stats->unallocated_overpayment_amt -= $event->amount;
        $stats->remaining_balance_amt += $event->amount;
        $stats->save();
    }

    protected function getStats(string $accountId): AccountStatistics
    {
        return AccountStatistics::firstOrNew(['account_id' => $accountId]);
    }

    protected function updateReportingMetrics(AccountStatistics $stats)
    {
        // Total Invoice Amount Calculation
        $stats->total_invoices_amt = $stats->total_principal_billed_amt + $stats->total_late_charge_billed_amt;

        // MIA Calculation
        $avgBilled = AccountInvoice::where('account_id', $stats->account_id)
            ->latest('occured_at')
            ->limit(3)
            ->avg('principal_billed_amt') ?: 1;
        
        $stats->mia_score = (int) ceil($stats->remaining_principal_amt / max(1, $avgBilled));
        
        // Delinquency Status
        $stats->is_delinquent = $stats->remaining_balance_amt > 0;
        
        // Risk Level
        $stats->risk_level = match (true) {
            $stats->mia_score >= 3 => 'High',
            $stats->mia_score >= 1 => 'Medium',
            default => 'Low',
        };

        // Collection Rate
        $totalBilled = $stats->total_principal_billed_amt + $stats->total_late_charge_billed_amt;
        $netCollected = $stats->total_payments_amt - $stats->total_refunded_amt;
        $stats->collection_rate = round(($netCollected / max(1, $totalBilled)) * 100, 2);
    }

    public function onStartingEventReplay()
    {
        AccountStatistics::truncate();
    }
}
