<?php

namespace App\Projectors;

use App\Enums\AccountCommandTypeEnum;
use App\Events\Credits\CreditNoteIssued;
use App\Events\Invoices\InvoiceCreated;
use App\Events\Invoices\LateChargeApplied;
use App\Events\Payments\PaymentReceived;
use App\Events\Refunds\RefundIssued;
use App\Models\AccountStatement;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class StatementOfAccountProjector extends Projector
{
    public function onInvoiceCreated(InvoiceCreated $event)
    {
        $this->recordTransaction(
            accountId: $event->accountId,
            referenceNo: $event->referenceNo,
            type: ucfirst($event->type),
            occuredAt: $event->occuredAt,
            debit: $event->amount,
            credit: 0,
            description: ($event->type == AccountCommandTypeEnum::INVOICE->value) ? 'Monthly Charge ' : 'Other Charge',
        );
    }

    public function onLateChargeApplied(LateChargeApplied $event)
    {
        $this->recordTransaction(
            accountId: $event->accountId,
            referenceNo: $event->referenceNo,
            type: AccountCommandTypeEnum::LATE_CHARGE->value,
            occuredAt: $event->occuredAt,
            debit: $event->amount,
            credit: 0,
            description: "Late Charge for {$event->invoiceNo}",
        );
    }

    public function onPaymentReceived(PaymentReceived $event)
    {
        $this->recordTransaction(
            accountId: $event->accountId,
            referenceNo: $event->referenceNo,
            type: AccountCommandTypeEnum::PAYMENT->value,
            occuredAt: $event->occuredAt,
            debit: 0,
            credit: $event->amount,
            description: 'Customer Payment',
        );
    }

    public function onCreditNoteIssued(CreditNoteIssued $event)
    {
        $this->recordTransaction(
            accountId: $event->accountId,
            referenceNo: $event->referenceNo,
            type: AccountCommandTypeEnum::CREDIT_NOTE->value,
            occuredAt: $event->occuredAt,
            debit: 0,
            credit: $event->amount,
            description: $event->invoiceNo ? "Credit Note for {$event->invoiceNo}" : 'Credit Note issued',
        );
    }

    public function onRefundIssued(RefundIssued $event)
    {
        // Refund increases the customer's debt (or reduces their overpayment credit)
        $this->recordTransaction(
            accountId: $event->accountId,
            referenceNo: $event->referenceNo,
            type: AccountCommandTypeEnum::REFUND->value,
            occuredAt: $event->occuredAt,
            debit: $event->amount,
            credit: 0,
            description: 'Refund issued to customer',
        );
    }

    protected function recordTransaction(
        string $accountId,
        string $referenceNo,
        string $type,
        string $occuredAt,
        int $debit,
        int $credit,
        string $description
    ) {
        $balanceImpact = $debit - $credit;
        
        $latestStatement = AccountStatement::where('account_id', $accountId)
            ->latest('id')
            ->first();

        $currentBalance = $latestStatement ? $latestStatement->running_balance : 0;
        $newBalance = $currentBalance + $balanceImpact;

        if($type === AccountCommandTypeEnum::INVOICE->value) {
            $invoiceCount = AccountStatement::query()
                ->where('account_id', $accountId)
                ->where('type', AccountCommandTypeEnum::INVOICE->value)
                ->count();
            $description .= ($invoiceCount + 1);
        }

        AccountStatement::create([
            'account_id' => $accountId,
            'reference_no' => $referenceNo,
            'type' => $type,
            'occured_at' => $occuredAt,
            'debit_amt' => $debit,
            'credit_amt' => $credit,
            'balance_impact' => $balanceImpact,
            'running_balance' => $newBalance,
            'description' => $description,
        ]);
    }
}
