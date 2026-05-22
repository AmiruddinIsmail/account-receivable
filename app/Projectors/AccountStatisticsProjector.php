<?php

namespace App\Projectors;

use App\Enums\AccountAllocationComponentEnum;
use App\Enums\AccountInvoiceStatusEnum;
use App\Events\Credits\CreditNoteAllocated;
use App\Events\Credits\CreditNoteAllocationReversed;
use App\Events\Credits\CreditNoteIssued;
use App\Events\Credits\CreditNoteVoided;
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
        $stats->billed_principal_amt += $event->amount;
        $stats->remaining_balance_amt += $event->amount;
        $stats->remaining_principal_amt += $event->amount;
        $stats->last_invoice_at = Carbon::parse($event->occurredAt);
        $stats->last_event_at = Carbon::parse($event->occurredAt);

        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onLateChargeApplied(LateChargeApplied $event)
    {
        $stats = $this->getStats($event->accountId);

        $stats->billed_late_charge_amt += $event->amount;
        $stats->remaining_balance_amt += $event->amount;
        $stats->remaining_late_charge_amt += $event->amount;
        $stats->last_event_at = Carbon::parse($event->occurredAt);

        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onPaymentReceived(PaymentReceived $event)
    {
        $stats = $this->getStats($event->accountId);

        $stats->payment_amt += $event->amount;
        // PaymentReceived doesn't immediately reduce balance if not allocated?
        // Actually, balance should reflect total debt - total payments.
        // $stats->remaining_balance_amt -= $event->amount;

        $stats->last_payment_at = Carbon::parse($event->occurredAt);
        $stats->last_event_at = Carbon::parse($event->occurredAt);

        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onRefundIssued(RefundIssued $event)
    {
        $stats = $this->getStats($event->accountId);

        $stats->refund_amt += $event->amount;
        $stats->last_event_at = Carbon::parse($event->occurredAt);

        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onCreditNoteIssued(CreditNoteIssued $event)
    {
        $stats = $this->getStats($event->accountId);

        $stats->credit_amt += $event->amount;
        $stats->last_event_at = Carbon::parse($event->occurredAt);

        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onPaymentAllocated(PaymentAllocated $event)
    {
        $stats = $this->getStats($event->accountId);
        $stats->payment_allocated_total_amt += $event->amount;

        $stats->remaining_balance_amt -= $event->amount;

        if ($event->component === AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value) {
            $stats->payment_allocated_principal_amt += $event->amount;
            $stats->remaining_principal_amt -= $event->amount;
        } elseif ($event->component === AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value) {
            $stats->payment_allocated_late_charge_amt += $event->amount;
            $stats->remaining_late_charge_amt -= $event->amount;
        }
        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onOverpaymentAllocated(OverpaymentAllocated $event)
    {
        $stats = $this->getStats($event->accountId);
        $stats->payment_allocated_total_amt += $event->amount;
        $stats->unused_overpayment_amt -= $event->amount;

        $stats->remaining_balance_amt -= $event->amount;

        if ($event->component === AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value) {
            $stats->payment_allocated_principal_amt += $event->amount;
            $stats->remaining_principal_amt -= $event->amount;
        } elseif ($event->component === AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value) {
            $stats->payment_allocated_late_charge_amt += $event->amount;
            $stats->remaining_late_charge_amt -= $event->amount;
        }
        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onCreditNoteAllocated(CreditNoteAllocated $event)
    {
        $stats = $this->getStats($event->accountId);
        $stats->credit_allocated_total_amt += $event->amount;

        $stats->remaining_balance_amt -= $event->amount;

        if ($event->component === AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value) {
            $stats->credit_allocated_principal_amt += $event->amount;
            $stats->remaining_principal_amt -= $event->amount;
        } elseif ($event->component === AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value) {
            $stats->credit_allocated_late_charge_amt += $event->amount;
            $stats->remaining_late_charge_amt -= $event->amount;
        }

        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onPaymentAllocationReversed(PaymentAllocationReversed $event)
    {
        $stats = $this->getStats($event->accountId);
        $stats->payment_allocated_total_amt -= $event->amount;
        $stats->remaining_balance_amt += $event->amount;

        if ($event->component === AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value) {
            $stats->payment_allocated_principal_amt -= $event->amount;
            $stats->remaining_principal_amt += $event->amount;
        } elseif ($event->component === AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value) {
            $stats->payment_allocated_late_charge_amt -= $event->amount;
            $stats->remaining_late_charge_amt += $event->amount;
        }
        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onOverpaymentCreated(OverpaymentCreated $event)
    {
        $stats = $this->getStats($event->accountId);
        $stats->unused_overpayment_amt += $event->amount;
        $stats->save();
    }

    public function onOverpaymentRefunded(OverpaymentRefunded $event)
    {
        $stats = $this->getStats($event->accountId);
        $stats->unused_overpayment_amt -= $event->amount;
        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onCreditNoteAllocationReversed(CreditNoteAllocationReversed $event)
    {
        $stats = $this->getStats($event->accountId);
        $stats->credit_allocated_total_amt -= $event->amount;
        $stats->remaining_balance_amt += $event->amount;

        if ($event->component === AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value) {
            $stats->credit_allocated_principal_amt -= $event->amount;
            $stats->remaining_principal_amt += $event->amount;
        }

        if ($event->component === AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value) {
            $stats->credit_allocated_late_charge_amt -= $event->amount;
            $stats->remaining_late_charge_amt += $event->amount;
        }

        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    public function onCreditNoteVoided(CreditNoteVoided $event)
    {
        $stats = $this->getStats($event->accountId);
        $stats->credit_voided_amt += $event->amount;

        /** only unused remaining credit affects balance here  */
        $stats->remaining_balance_amt += $event->amount;

        $stats->last_event_at = Carbon::parse($event->occurredAt);

        $this->updateReportingMetrics($stats);
        $stats->save();
    }

    protected function getStats(string $accountId): AccountStatistics
    {
        return AccountStatistics::firstOrNew(['account_id' => $accountId]);
    }

    protected function updateReportingMetrics(AccountStatistics $stats)
    {
        /** * ===================================== * BILLING TOTALS * ===================================== */
        $stats->billed_total_amt = $stats->billed_principal_amt + $stats->billed_late_charge_amt;

        /** * ===================================== * UNUSED CREDIT * ===================================== * *
         * Credit issued but not allocated yet.
         * Formula: issued credits - allocated credits - voided credits
         * */
        $stats->unused_credit_amt = max(0, $stats->credit_amt - $stats->credit_allocated_total_amt - $stats->credit_voided_amt);

        /** * ===================================== * REMAINING BALANCES * ===================================== *
         * Outstanding invoice balances after allocations applied.
         * */
        $stats->remaining_principal_amt = max(
            0,
            $stats->billed_principal_amt
            - $stats->payment_allocated_principal_amt
            - $stats->credit_allocated_principal_amt
        );

        $stats->remaining_late_charge_amt = max(
            0,
            $stats->billed_late_charge_amt
            - $stats->payment_allocated_late_charge_amt
            - $stats->credit_allocated_late_charge_amt
        );

        $stats->remaining_balance_amt = $stats->remaining_principal_amt + $stats->remaining_late_charge_amt;

        /** * ===================================== * NET CUSTOMER POSITION * ===================================== *
         * positive: customer owes company
         * negative: company owes customer
         * */
        $stats->net_balance_amt = $stats->remaining_balance_amt - $stats->unused_overpayment_amt - $stats->unused_credit_amt;

        /** * ===================================== * MIA CALCULATION * ===================================== */
        $avgBilled = AccountInvoice::query()
            ->where('account_id', $stats->account_id)
            ->latest('occurred_at')
            ->limit(3)
            ->avg('principal_billed_amt') ?: 1;

        $stats->mia_score = (int) ceil($stats->remaining_principal_amt / max(1, $avgBilled));

        /** * ===================================== * DELINQUENCY * ===================================== */
        $stats->is_delinquent = $stats->net_balance_amt > 0;

        /** * ===================================== * RISK LEVEL * ===================================== */
        $stats->risk_level = match (true) {
            $stats->mia_score >= 3 => 'High', $stats->mia_score >= 1 => 'Medium', default => 'Low',
        };

        /** * ===================================== * COLLECTION RATE * ===================================== *
         * Cash collection effectiveness.
         * */
        $totalBilled = $stats->billed_principal_amt + $stats->billed_late_charge_amt;
        $netCollected = $stats->payment_amt - $stats->refund_amt;
        $stats->collection_rate = round(($netCollected / max(1, $totalBilled)) * 100, 2);

        /** * ===================================== * OLDEST OPEN INVOICE DATES * ===================================== */
        if ($stats->remaining_balance_amt > 0) {

            $stats->oldest_open_principal_invoice_at = AccountInvoice::query()->where('account_id', $stats->account_id)->where('principal_status', AccountInvoiceStatusEnum::OPEN->value)->min('occurred_at');
            $stats->oldest_open_late_charge_invoice_at = AccountInvoice::query()->where('account_id', $stats->account_id)->where('late_charge_status', AccountInvoiceStatusEnum::OPEN->value)->min('occurred_at');

        } else {
            $stats->oldest_open_principal_invoice_at = null;
            $stats->oldest_open_late_charge_invoice_at = null;
        }
    }

    public function onStartingEventReplay()
    {
        AccountStatistics::truncate();
    }
}
