<?php

namespace App\Aggregates;

use App\Enums\AccountAllocationComponentEnum;
use App\Enums\AccountAllocationSourceTypeEnum;
use App\Enums\AccountCommandTypeEnum;
use App\Enums\AccountInvoiceStatusEnum;
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
use Exception;
use Illuminate\Support\Str;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class AccountAggregate extends AggregateRoot
{
    public $invoices;

    public $availableOverpayments;

    public $availableCredits;

    public $allocations;

    public $payments;

    public $issuedCreditNotes;

    public $appliedLateCharges;

    public function __construct()
    {
        $this->invoices = [];
        $this->availableOverpayments = [];
        $this->availableCredits = [];
        $this->allocations = [];
        $this->payments = [];
        $this->issuedCreditNotes = [];
        $this->appliedLateCharges = [];
    }

    public function invoiceCreated(string $referenceNo, string $occuredAt, int $amount, string $type = AccountCommandTypeEnum::INVOICE->value)
    {
        if (isset($this->invoices[$referenceNo])) {
            throw new Exception('Duplicate invoice');
        }

        if ($amount <= 0) {
            throw new Exception('Invalid amount');
        }

        $this->recordThat(new InvoiceCreated(
            accountId: $this->uuid(),
            referenceNo: $referenceNo,
            amount: $amount,
            occuredAt: $occuredAt,
            type: $type,
        ));

        // Auto apply unused overpayment
        $remaining = $this->overpaymentAllocations($referenceNo, $amount, AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value, $occuredAt);

        // If got remaining amount & got unused credit note apply next
        $this->creditNoteAllocations($referenceNo, $remaining, AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value, $occuredAt);

        return $this;
    }

    public function lateChargeApplied(string $referenceNo, string $occuredAt, int $amount, string $invoiceNo)
    {
        if (isset($this->appliedLateCharges[$referenceNo])) {
            throw new Exception('Duplicate late charge');
        }

        if (! isset($this->invoices[$invoiceNo])) {
            throw new Exception('Invoice not found');
        }

        if ($amount <= 0) {
            throw new Exception('Invalid amount');
        }

        $this->recordThat(new LateChargeApplied(
            accountId: $this->uuid(),
            referenceNo: $referenceNo,
            amount: $amount,
            occuredAt: $occuredAt,
            invoiceNo: $invoiceNo,
        ));

        // Auto apply unused overpayment
        $remaining = $this->overpaymentAllocations($invoiceNo, $amount, AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value, $occuredAt);

        // If got remaining amount & got unused credit note apply next
        $this->creditNoteAllocations($invoiceNo, $remaining, AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value, $occuredAt);

        return $this;
    }

    public function paymentReceived(string $referenceNo, string $occuredAt, int $amount)
    {
        if (isset($this->payments[$referenceNo])) {
            throw new Exception('Duplicate payment');
        }

        if ($amount <= 0) {
            throw new Exception('Invalid amount');
        }

        $this->recordThat(new PaymentReceived(
            accountId: $this->uuid(),
            referenceNo: $referenceNo,
            amount: $amount,
            occuredAt: $occuredAt,
        ));

        $this->paymentAllocations($referenceNo, $amount, $occuredAt);

        return $this;
    }

    public function creditNoteIssued(string $referenceNo, string $occuredAt, int $amount, ?string $invoiceNo = null)
    {
        if ($amount <= 0) {
            throw new Exception('Invalid amount');
        }

        if (isset($this->issuedCreditNotes[$referenceNo])) {
            throw new Exception('Duplicate credit note');
        }

        if ($invoiceNo !== null) {
            if (! isset($this->invoices[$invoiceNo])) {
                throw new Exception('Invoice not found');
            }

            $invoice = $this->invoices[$invoiceNo];

            if ($invoice['status'] === AccountInvoiceStatusEnum::CLOSED->value) {
                throw new Exception('Invoice is closed');
            }

            if ($amount > $this->invoiceBalance($invoice)) {
                throw new Exception('Amount exceeds invoice balance');
            }
        }

        $this->recordThat(new CreditNoteIssued(
            accountId: $this->uuid(),
            referenceNo: $referenceNo,
            amount: $amount,
            occuredAt: $occuredAt,
            invoiceNo: $invoiceNo,
        ));

        if ($invoiceNo !== null) {
            // Fix: Specify exactly this credit note to be allocated instead of looping all
            $amountToAllocate = min($amount, $this->invoicePrincipalBalance($this->invoices[$invoiceNo]));

            $this->recordThat(new CreditNoteAllocated(
                accountId: $this->uuid(),
                invoiceNo: $invoiceNo,
                amount: $amountToAllocate,
                component: AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value,
                referenceNo: $referenceNo,
                allocationId: (string) Str::uuid(),
                occuredAt: $occuredAt,
            ));

            $remAmount = $amount - $amountToAllocate;
            $lpcBalance = $this->invoiceLateChargeBalance($this->invoices[$invoiceNo]);
            $lpcAllocate = min($remAmount, $lpcBalance);

            if ($lpcAllocate > 0) {
                $this->recordThat(new CreditNoteAllocated(
                    accountId: $this->uuid(),
                    invoiceNo: $invoiceNo,
                    amount: $lpcAllocate,
                    component: AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value,
                    referenceNo: $referenceNo,
                    allocationId: (string) Str::uuid(),
                    occuredAt: $occuredAt,
                ));
            }
        }

        return $this;
    }

    public function refundIssued(string $referenceNo, string $occuredAt, int $amount)
    {
        if ($amount <= 0) {
            throw new Exception('Invalid amount');
        }

        // Validate refund limits before recording any events
        $availableForRefund = collect($this->availableOverpayments)->sum('remaining');

        $reversedMap = collect($this->allocations)
            ->where('sourceType', AccountAllocationSourceTypeEnum::PAYMENT_REVERSAL->value)
            ->groupBy('allocationId')
            ->map(fn ($rows) => $rows->sum('amount')); // negative values

        $availableFromAllocations = 0;
        foreach ($this->allocations as $alloc) {
            if (in_array($alloc['sourceType'], [
                AccountAllocationSourceTypeEnum::PAYMENT->value,
                AccountAllocationSourceTypeEnum::OVERPAYMENT->value,
            ])) {
                $allocationId = $alloc['id'];
                $alreadyReversed = $reversedMap[$allocationId] ?? 0;
                $available = $alloc['amount'] + $alreadyReversed;
                if ($available > 0) {
                    $availableFromAllocations += $available;
                }
            }
        }

        if ($amount > ($availableForRefund + $availableFromAllocations)) {
            throw new Exception('Refund exceeds refundable amount');
        }

        $this->recordThat(new RefundIssued(
            accountId: $this->uuid(),
            referenceNo: $referenceNo,
            amount: $amount,
            occuredAt: $occuredAt,
        ));

        // STEP 1: CONSUME OVERPAYMENT FIRST
        $remaining = $this->overpaymentReversal($referenceNo, $amount);

        // STEP 2: REVERSE PAYMENT ALLOCATIONS (LIFO) // Include overpayment allocations as well
        $remaining = $this->paymentAllocationReversal($referenceNo, $remaining, $occuredAt);

        return $this;
    }

    /**
     * ===================================
     * Apply Method
     * ===================================
     */
    public function applyInvoiceCreated(InvoiceCreated $event)
    {
        $this->invoices[$event->referenceNo] = [
            'invoiceNo' => $event->referenceNo,
            'occuredAt' => $event->occuredAt,
            'principalAmt' => $event->amount,
            'lateChargeAmt' => 0,
            'principalPaid' => 0,
            'lateChargePaid' => 0,
            'status' => AccountInvoiceStatusEnum::OPEN->value,
        ];
    }

    public function applyLateChargeApplied(LateChargeApplied $event)
    {
        $this->appliedLateCharges[$event->referenceNo] = true;
        $this->invoices[$event->invoiceNo]['lateChargeAmt'] += $event->amount;

        // Re-evaluate invoice status
        if ($this->invoiceBalance($this->invoices[$event->invoiceNo]) > 0) {
            $this->invoices[$event->invoiceNo]['status'] = AccountInvoiceStatusEnum::OPEN->value;
        }
    }

    public function applyPaymentReceived(PaymentReceived $event)
    {
        $this->payments[$event->referenceNo] = [
            'paymentNo' => $event->referenceNo,
            'amount' => $event->amount,
            'occuredAt' => $event->occuredAt,
        ];
    }

    public function applyPaymentAllocated(PaymentAllocated $event)
    {
        $this->paymentPaidToInvoices($event->invoiceNo, $event->amount, $event->component);

        $this->allocations[] = [
            'id' => $event->allocationId,
            'sourceType' => AccountAllocationSourceTypeEnum::PAYMENT->value,
            'sourceNo' => $event->referenceNo,
            'invoiceNo' => $event->invoiceNo,
            'component' => $event->component,
            'amount' => $event->amount,
        ];
    }

    public function applyOverpaymentAllocated(OverpaymentAllocated $event)
    {
        $this->paymentPaidToInvoices($event->invoiceNo, $event->amount, $event->component);

        if (isset($this->availableOverpayments[$event->referenceNo])) {
            $this->availableOverpayments[$event->referenceNo]['remaining'] -= $event->amount;
            $this->filterAvailableOverpayments($event->referenceNo);
        }

        $this->allocations[] = [
            'id' => $event->allocationId,
            'sourceType' => AccountAllocationSourceTypeEnum::OVERPAYMENT->value,
            'sourceNo' => $event->referenceNo,
            'invoiceNo' => $event->invoiceNo,
            'component' => $event->component,
            'amount' => $event->amount,
        ];
    }

    public function applyOverpaymentCreated(OverpaymentCreated $event)
    {
        $this->availableOverpayments[$event->referenceNo] = [
            'paymentNo' => $event->referenceNo,
            'remaining' => $event->amount,
            'occuredAt' => $event->occuredAt,
        ];
    }

    public function applyCreditNoteIssued(CreditNoteIssued $event)
    {
        $this->issuedCreditNotes[$event->referenceNo] = true;
        $this->availableCredits[$event->referenceNo] = [
            'creditNoteNo' => $event->referenceNo,
            'remaining' => $event->amount,
            'occuredAt' => $event->occuredAt,
        ];
    }

    public function applyCreditNoteAllocated(CreditNoteAllocated $event)
    {
        $this->paymentPaidToInvoices($event->invoiceNo, $event->amount, $event->component);

        $this->availableCredits[$event->referenceNo]['remaining'] -= $event->amount;
        $this->filterAvailableCredits($event->referenceNo);
        // generate in command
        $this->allocations[] = [
            'id' => $event->allocationId,
            'sourceType' => AccountAllocationSourceTypeEnum::CREDIT_NOTE->value,
            'sourceNo' => $event->referenceNo,
            'invoiceNo' => $event->invoiceNo,
            'component' => $event->component,
            'amount' => $event->amount,
        ];
    }

    public function applyRefundIssued(RefundIssued $event) {}

    public function applyPaymentAllocationReversed(PaymentAllocationReversed $event)
    {
        $invoiceNo = $event->invoiceNo;

        $invoice = &$this->invoices[$invoiceNo];

        match ($event->component) {
            AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value => $invoice['principalPaid'] -= $event->amount,
            AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value => $invoice['lateChargePaid'] -= $event->amount,
            default => throw new Exception("Unsupported component: {$event->component}"),
        };

        // safety (avoid negative)
        $invoice['principalPaid'] = max(0, $invoice['principalPaid']);
        $invoice['lateChargePaid'] = max(0, $invoice['lateChargePaid']);

        // set status open if paid less that principal
        if ($this->invoiceBalance($invoice) > 0) {
            $invoice['status'] = AccountInvoiceStatusEnum::OPEN->value;
        }

        // track reversal in allocation history
        $this->allocations[] = [
            'id' => $event->id,
            'sourceType' => AccountAllocationSourceTypeEnum::PAYMENT_REVERSAL->value,
            'allocationId' => $event->allocationId,
            'sourceNo' => $event->paymentNo,
            'invoiceNo' => $invoiceNo,
            'component' => $event->component,
            'amount' => -$event->amount,
        ];
    }

    public function applyOverpaymentRefunded(OverpaymentRefunded $event)
    {
        if (! isset($this->availableOverpayments[$event->paymentNo])) {
            return;
        }

        $this->availableOverpayments[$event->paymentNo]['remaining'] -= $event->amount;

        if ($this->availableOverpayments[$event->paymentNo]['remaining'] <= 0) {
            unset($this->availableOverpayments[$event->paymentNo]);
        }
    }

    protected function paymentPaidToInvoices(string $invoiceNo, int $amount, string $component)
    {
        if (! isset($this->invoices[$invoiceNo])) {
            throw new Exception("Invoice {$invoiceNo} not found");
        }

        $invoice = &$this->invoices[$invoiceNo];

        if ($component === AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value) {
            $invoice['principalPaid'] += $amount;
        } elseif ($component === AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value) {
            $invoice['lateChargePaid'] += $amount;
        }

        if ($this->invoiceBalance($invoice) <= 0) {
            $invoice['status'] = AccountInvoiceStatusEnum::CLOSED->value;
        }
    }

    protected function filterAvailableOverpayments(string $paymentNo)
    {
        $inv = $this->availableOverpayments[$paymentNo];

        if (
            $inv['remaining'] <= 0
        ) {
            unset($this->availableOverpayments[$paymentNo]);
        }
    }

    protected function filterAvailableCredits(string $creditNoteNo)
    {
        $inv = $this->availableCredits[$creditNoteNo];

        if (
            $inv['remaining'] <= 0
        ) {
            unset($this->availableCredits[$creditNoteNo]);
        }
    }

    protected function paymentAllocations(string $paymentNo, int $amount, string $occuredAt)
    {
        $remaining = $amount;

        $invoices = collect($this->invoices)->where('status', AccountInvoiceStatusEnum::OPEN->value)->sortBy('occuredAt');

        /*
        |--------------------------------------------------------------------------
        | PASS 1: PAY ALL PRINCIPAL FIRST (FIFO)
        |--------------------------------------------------------------------------
        */
        foreach ($invoices as $invoice) {

            if ($remaining <= 0) {
                break;
            }

            $principalOutstanding =
                $invoice['principalAmt'] - $invoice['principalPaid'];

            if ($principalOutstanding <= 0) {
                continue;
            }

            $pay = min($principalOutstanding, $remaining);

            $this->recordThat(new PaymentAllocated(
                accountId: $this->uuid(),
                referenceNo: $paymentNo,
                invoiceNo: $invoice['invoiceNo'],
                allocationId: (string) Str::uuid(),
                component: AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value,
                amount: $pay,
                occuredAt: $occuredAt,
            ));

            $remaining -= $pay;
        }

        /*
        |--------------------------------------------------------------------------
        | PASS 2: PAY ALL LATE CHARGES (FIFO)
        |--------------------------------------------------------------------------
        */
        foreach ($invoices as $invoice) {

            if ($remaining <= 0) {
                break;
            }

            $lpcOutstanding =
                $invoice['lateChargeAmt'] - $invoice['lateChargePaid'];

            if ($lpcOutstanding <= 0) {
                continue;
            }

            $pay = min($lpcOutstanding, $remaining);

            $this->recordThat(new PaymentAllocated(
                accountId: $this->uuid(),
                referenceNo: $paymentNo,
                invoiceNo: $invoice['invoiceNo'],
                allocationId: (string) Str::uuid(),
                component: AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value,
                amount: $pay,
                occuredAt: $occuredAt,
            ));

            $remaining -= $pay;
        }

        /*
        |--------------------------------------------------------------------------
        | LEFTOVER → OVERPAYMENT
        |--------------------------------------------------------------------------
        */
        if ($remaining > 0) {
            $this->recordThat(new OverpaymentCreated(
                accountId: $this->uuid(),
                referenceNo: $paymentNo,
                amount: $remaining,
                occuredAt: $occuredAt,
            ));
        }
    }

    // Fix: Parameter renamed from $referenceNo to $invoiceNo
    protected function overpaymentAllocations(string $invoiceNo, int $amount, string $component, ?string $occuredAt = null)
    {
        $remaining = $amount;
        foreach (collect($this->availableOverpayments)->sortBy('occuredAt') as $overpayment) {
            if ($remaining <= 0) {
                break;
            }

            $apply = min($remaining, $overpayment['remaining']);

            // Fix: Use OverpaymentAllocated event
            $this->recordThat(new OverpaymentAllocated(
                accountId: $this->uuid(),
                referenceNo: $overpayment['paymentNo'],
                invoiceNo: $invoiceNo,
                amount: $apply,
                component: $component,
                allocationId: (string) Str::uuid(),
                occuredAt: $occuredAt,
            ));

            $remaining -= $apply;
        }

        return $remaining;
    }

    // Fix: Parameter renamed from $referenceNo to $invoiceNo
    protected function creditNoteAllocations(string $invoiceNo, int $amount, string $component, ?string $occuredAt = null)
    {
        $remaining = $amount;
        foreach (collect($this->availableCredits)->sortBy('occuredAt') as $credit) {
            if ($remaining <= 0) {
                break;
            }

            $apply = min($remaining, $credit['remaining']);

            $this->recordThat(new CreditNoteAllocated(
                accountId: $this->uuid(),
                invoiceNo: $invoiceNo,
                amount: $apply,
                component: $component,
                referenceNo: $credit['creditNoteNo'],
                allocationId: (string) Str::uuid(),
                occuredAt: $occuredAt,
            ));

            $remaining -= $apply;
        }

        return $remaining;
    }

    protected function overpaymentReversal(string $referenceNo, int $amount)
    {
        $remaining = $amount;
        foreach (collect($this->availableOverpayments)->sortByDesc('occuredAt') as $op) {

            if ($remaining <= 0) {
                break;
            }

            $consume = min($remaining, $op['remaining']);

            $this->recordThat(new OverpaymentRefunded(
                accountId: $this->uuid(),
                paymentNo: $op['paymentNo'],
                referenceNo: $referenceNo,
                amount: $consume,
            ));

            $remaining -= $consume;
        }

        return $remaining;
    }

    protected function paymentAllocationReversal(string $referenceNo, int $amount, string $occuredAt)
    {
        $reversedMap = collect($this->allocations)
            ->where('sourceType', AccountAllocationSourceTypeEnum::PAYMENT_REVERSAL->value)
            ->groupBy('allocationId')
            ->map(fn ($rows) => $rows->sum('amount')); // negative values

        $remaining = $amount;
        foreach (array_reverse($this->allocations) as $alloc) {

            if ($remaining <= 0) {
                break;
            }

            // Fix: Include overpayment allocations for reversal as well
            if (! in_array($alloc['sourceType'], [
                AccountAllocationSourceTypeEnum::PAYMENT->value,
                AccountAllocationSourceTypeEnum::OVERPAYMENT->value,
            ])) {
                continue;
            }

            $allocationId = $alloc['id'];

            $alreadyReversed = $reversedMap[$allocationId] ?? 0;

            $available = $alloc['amount'] + $alreadyReversed;
            if ($available <= 0) {
                continue;
            }

            $reversal = min($remaining, $available);

            $this->recordThat(new PaymentAllocationReversed(
                accountId: $this->uuid(),
                allocationId: $allocationId,
                paymentNo: $alloc['sourceNo'],
                referenceNo: $referenceNo,
                invoiceNo: $alloc['invoiceNo'],
                component: $alloc['component'],
                amount: $reversal,
                id: (string) Str::uuid(),
                occuredAt: $occuredAt,
            ));
            $remaining -= $reversal;
        }

        return $remaining;
    }

    protected function invoiceBalance(array $invoice): int
    {
        return
            ($invoice['principalAmt'] - $invoice['principalPaid']) +
            ($invoice['lateChargeAmt'] - $invoice['lateChargePaid']);
    }

    protected function invoicePrincipalBalance(array $invoice): int
    {
        return $invoice['principalAmt'] - $invoice['principalPaid'];
    }

    protected function invoiceLateChargeBalance(array $invoice): int
    {
        return $invoice['lateChargeAmt'] - $invoice['lateChargePaid'];
    }
}
