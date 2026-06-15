<?php

namespace App\Projectors;

use App\Enums\AccountAllocationComponentEnum;
use App\Enums\AccountInvoiceStatusEnum;
use App\Events\Credits\CreditNoteAllocated;
use App\Events\Credits\CreditNoteAllocationReversed;
use App\Events\Invoices\InvoiceCreated;
use App\Events\Invoices\LateChargeApplied;
use App\Events\Payments\OverpaymentAllocated;
use App\Events\Payments\PaymentAllocated;
use App\Events\Refunds\PaymentAllocationReversed;
use App\Models\AccountInvoice;
use Illuminate\Support\Carbon;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class InvoiceProjector extends Projector
{
    public function onInvoiceCreated(InvoiceCreated $event)
    {
        AccountInvoice::create([
            'account_id' => $event->accountId,
            'reference_no' => $event->referenceNo,
            'occurred_at' => $event->occurredAt,
            'due_at' => Carbon::parse($event->occurredAt)->addDay(),
            'principal_billed_amt' => $event->amount,
            'late_charge_billed_amt' => 0,
            'principal_paid_amt' => 0,
            'late_charge_paid_amt' => 0,
            'principal_credit_amt' => 0,
            'late_charge_credit_amt' => 0,
            'principal_status' => AccountInvoiceStatusEnum::OPEN->value,
            'late_charge_status' => AccountInvoiceStatusEnum::CLOSED->value,
            'status' => AccountInvoiceStatusEnum::OPEN->value,
            'type' => $event->type,
            'notes' => $event->notes ?? null,
            'tenure' => $event->tenure,
        ]);
    }

    public function onLateChargeApplied(LateChargeApplied $event)
    {
        $invoice = AccountInvoice::query()
            ->where('account_id', $event->accountId)
            ->where('reference_no', $event->invoiceNo)
            ->first();

        if (! $invoice) {
            return;
        }

        $invoice->update([
            'late_charge_billed_amt' => $invoice->late_charge_billed_amt + $event->amount,
            'late_charge_status' => AccountInvoiceStatusEnum::OPEN->value,
        ]);
    }

    public function onPaymentAllocated(PaymentAllocated $event)
    {
        $invoice = AccountInvoice::query()
            ->where('account_id', $event->accountId)
            ->where('reference_no', $event->invoiceNo)
            ->first();

        if (! $invoice) {
            return;
        }

        if ($event->component === AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value) {
            $invoice->resolvedPrincipalPaid($event->amount);
        }

        if ($event->component === AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value) {
            $invoice->resolvedLateChargePaid($event->amount);
        }
    }

    public function onOverpaymentAllocated(OverpaymentAllocated $event)
    {
        $invoice = AccountInvoice::query()
            ->where('account_id', $event->accountId)
            ->where('reference_no', $event->invoiceNo)
            ->first();

        if (! $invoice) {
            return;
        }

        if ($event->component === AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value) {
            $invoice->resolvedPrincipalPaid($event->amount);
        }

        if ($event->component === AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value) {
            $invoice->resolvedLateChargePaid($event->amount);
        }
    }

    public function onCreditNoteAllocated(CreditNoteAllocated $event)
    {
        $invoice = AccountInvoice::query()
            ->where('account_id', $event->accountId)
            ->where('reference_no', $event->invoiceNo)
            ->first();

        if (! $invoice) {
            return;
        }

        if ($event->component === AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value) {
            $invoice->resolvedPrincipalPaid($event->amount);
            $invoice->appliedCreditToPrincipalAmount($event->amount);
        }

        if ($event->component === AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value) {
            $invoice->resolvedLateChargePaid($event->amount);
            $invoice->appliedCreditToLateChargeAmount($event->amount);
        }
    }

    public function onPaymentAllocationReversed(PaymentAllocationReversed $event)
    {
        $invoice = AccountInvoice::query()
            ->where('account_id', $event->accountId)
            ->where('reference_no', $event->invoiceNo)
            ->first();

        if (! $invoice) {
            return;
        }

        if ($event->component === AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value) {
            $invoice->subPrincipalPaid($event->amount);
        }
        if ($event->component === AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value) {
            $invoice->subLateChargePaid($event->amount);
        }
    }

    public function onCreditNoteAllocationReversed(CreditNoteAllocationReversed $event)
    {
        $invoice = AccountInvoice::query()
            ->where('account_id', $event->accountId)
            ->where('reference_no', $event->invoiceNo)
            ->first();

        if (! $invoice) {
            return;
        }

        if ($event->component === AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value) {
            $invoice->subPrincipalPaid($event->amount);
            $invoice->removeCreditToPrincipalAmount($event->amount);
        }
        if ($event->component === AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value) {
            $invoice->subLateChargePaid($event->amount);
            $invoice->removeCreditToLateChargeAmount($event->amount);
        }
    }
}
