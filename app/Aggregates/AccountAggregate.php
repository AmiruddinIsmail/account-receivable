<?php

namespace App\Aggregates;

use App\Enums\AccountAllocationComponentEnum;
use App\Enums\AccountAllocationSourceTypeEnum;
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
use App\Services\CreditNoteAllocator;
use App\Services\OverpaymentAllocator;
use App\Services\PaymentAllocator;
use App\Services\RefundAllocator;
use App\States\AllocationLedgerEntry;
use App\States\CreditNoteState;
use App\States\InvoiceState;
use App\States\OverpaymentState;
use App\States\PaymentState;
use Exception;
use Illuminate\Support\Str;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class AccountAggregate extends AggregateRoot
{
    /**
     * @var InvoiceState[]
     */
    protected array $invoices = [];

    /**
     * @var OverpaymentState[]
     */
    protected array $availableOverpayments = [];

    /**
     * @var CreditNoteState[]
     */
    protected array $availableCredits = [];

    /**
     * @var AllocationLedgerEntry[]
     */
    protected array $allocations = [];

    /**
     * @var PaymentState[]
     */
    protected array $payments = [];

    protected array $processedCreditNoteReferences = [];

    protected array $processedRefundReferences = [];

    protected array $processedLateChargeReferences = [];

    protected int $allocationSequence = 0;

    protected array $paymentAllocationBalances = [];

    protected array $creditAllocationBalances = [];

    protected array $voidedCreditNotes = [];

    protected PaymentAllocator $paymentAllocator;

    protected RefundAllocator $refundAllocator;

    protected CreditNoteAllocator $creditNoteAllocator;

    protected OverpaymentAllocator $overpaymentAllocator;

    public function __construct()
    {
        $this->paymentAllocator = new PaymentAllocator;
        $this->refundAllocator = new RefundAllocator;
        $this->creditNoteAllocator = new CreditNoteAllocator;
        $this->overpaymentAllocator = new OverpaymentAllocator;
    }

    public function invoiceCreated(
        string $referenceNo,
        string $occurredAt,
        int $amount,
        ?string $type,
        ?int $tenure,
        ?int $subscriptionAmt,
    ) {
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
            occurredAt: $occurredAt,
            type: $type,
            tenure: $tenure,
            subscriptionAmt: $subscriptionAmt,
        ));

        // Auto apply unused overpayment
        $remaining = $this->allocateOverpaymentToInvoice($referenceNo, $amount, AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value, $occurredAt);

        // If got remaining amount & got unused credit note apply next
        $this->allocateCreditNoteToInvoice($referenceNo, $remaining, AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value, $occurredAt);

        return $this;
    }

    public function lateChargeApplied(string $referenceNo, string $occurredAt, int $amount, string $invoiceNo)
    {
        if (isset($this->processedLateChargeReferences[$referenceNo])) {
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
            occurredAt: $occurredAt,
            invoiceNo: $invoiceNo,
        ));

        // Auto apply unused overpayment
        $remaining = $this->allocateOverpaymentToInvoice($invoiceNo, $amount, AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value, $occurredAt);

        // If got remaining amount & got unused credit note apply next
        $this->allocateCreditNoteToInvoice($invoiceNo, $remaining, AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value, $occurredAt);

        return $this;
    }

    public function paymentReceived(string $referenceNo, string $occurredAt, int $amount, ?int $tenure = null)
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
            occurredAt: $occurredAt,
            tenure: $tenure,
        ));

        $this->allocatePaymentToInvoices($referenceNo, $amount, $occurredAt);

        return $this;
    }

    public function creditNoteIssued(string $referenceNo, string $occurredAt, int $amount, ?string $invoiceNo = null, ?int $tenure = null)
    {
        if ($amount <= 0) {
            throw new Exception('Invalid amount');
        }

        if (isset($this->processedCreditNoteReferences[$referenceNo])) {
            throw new Exception('Duplicate credit note');
        }

        $principalAllocation = 0;
        $lateChargeAllocation = 0;

        if ($invoiceNo !== null) {

            if (! isset($this->invoices[$invoiceNo])) {
                throw new Exception('Invoice not found');
            }

            $invoice = $this->invoices[$invoiceNo];

            if ($invoice->status() === AccountInvoiceStatusEnum::CLOSED->value) {
                throw new Exception('Invoice is closed');
            } if ($amount > $invoice->balance()) {
                throw new Exception('Amount exceeds invoice balance');
            }

            $principalAllocation = min($amount, $invoice->principalBalance());
            $remaining = $amount - $principalAllocation;
            $lateChargeAllocation = min($remaining, $invoice->lateChargeBalance());

            $this->recordThat(new CreditNoteIssued(
                accountId: $this->uuid(),
                referenceNo: $referenceNo,
                amount: $amount,
                occurredAt: $occurredAt,
                invoiceNo: $invoiceNo,
                tenure: $tenure,
            ));

            if ($principalAllocation > 0) {
                $this->recordThat(new CreditNoteAllocated(
                    accountId: $this->uuid(),
                    invoiceNo: $invoiceNo,
                    amount: $principalAllocation,
                    component: AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value,
                    referenceNo: $referenceNo,
                    allocationId: (string) Str::uuid(),
                    occurredAt: $occurredAt
                ));

            } if ($lateChargeAllocation > 0) {
                $this->recordThat(new CreditNoteAllocated(
                    accountId: $this->uuid(),
                    invoiceNo: $invoiceNo,
                    amount: $lateChargeAllocation,
                    component: AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value,
                    referenceNo: $referenceNo,
                    allocationId: (string) Str::uuid(),
                    occurredAt: $occurredAt
                ));
            }

        } else {
            $this->recordThat(new CreditNoteIssued(
                accountId: $this->uuid(),
                referenceNo: $referenceNo,
                amount: $amount,
                occurredAt: $occurredAt,
                tenure: $tenure,
            ));

            $this->allocateCreditNoteToInvoices($referenceNo, $amount, $occurredAt);
        }

        return $this;
    }

    public function refundIssued(string $referenceNo, string $occurredAt, int $amount, ?int $tenure = null)
    {
        if ($amount <= 0) {
            throw new Exception('Invalid amount');
        }

        if (isset($this->processedRefundReferences[$referenceNo])) {
            throw new Exception('Duplicate refund');
        }

        // Validate refund limits before recording any events
        $refundableBalance = $this->calculateRefundableBalance();
        $diff = $amount - $refundableBalance;
        if ($amount > $refundableBalance && $diff > 500) {
            throw new Exception('Refund exceeds refundable amount. diff: '.$diff);
        }

        if ($diff > 0) {
            $amount -= $diff;
        }

        if ($amount <= 0) {
            return $this;
        }

        $this->recordThat(new RefundIssued(
            accountId: $this->uuid(),
            referenceNo: $referenceNo,
            amount: $amount,
            occurredAt: $occurredAt,
            tenure: $tenure,
        ));

        // STEP 1: CONSUME OVERPAYMENT FIRST
        $remaining = $this->refundOverpayments($referenceNo, $amount);

        // STEP 2: REVERSE PAYMENT ALLOCATIONS (LIFO) // Include overpayment allocations as well
        $this->reversePaymentAllocations($referenceNo, $remaining, $occurredAt);

        return $this;
    }

    public function voidCreditNote(string $referenceNo, string $occurredAt)
    {
        if (! isset($this->processedCreditNoteReferences[$referenceNo])) {
            throw new Exception('Credit note not found');
        }

        if (isset($this->voidedCreditNotes[$referenceNo])) {
            throw new Exception('Credit note already voided');
        }

        $reversals = $this->creditNoteAllocator->reverse(
            allocations: $this->allocations,
            allocationBalances: $this->creditAllocationBalances,
            referenceNo: $referenceNo,
        );

        foreach ($reversals as $reversal) {
            $this->recordThat(new CreditNoteAllocationReversed(
                accountId: $this->uuid(),
                allocationId: $reversal['allocationId'],
                referenceNo: $referenceNo,
                invoiceNo: $reversal['invoiceNo'],
                component: $reversal['component'],
                amount: $reversal['amount'],
                occurredAt: $occurredAt,
                id: (string) Str::uuid(),
            ));
        }

        $remainingCredit = $this->availableCredits[$referenceNo]->remaining ?? 0;

        if ($remainingCredit > 0) {
            $this->recordThat(new CreditNoteVoided(
                accountId: $this->uuid(),
                referenceNo: $referenceNo,
                amount: $remainingCredit,
                occurredAt: $occurredAt,
            ));
        }

        return $this;
    }

    /**
     * ===================================
     * Apply Method
     * ===================================
     */
    public function applyInvoiceCreated(InvoiceCreated $event)
    {
        $this->invoices[$event->referenceNo] = InvoiceState::create(
            invoiceNo: $event->referenceNo,
            occurredAt: $event->occurredAt,
            amount: $event->amount,
        );
    }

    public function applyLateChargeApplied(LateChargeApplied $event)
    {
        $this->processedLateChargeReferences[$event->referenceNo] = true;

        $invoice = $this->invoices[$event->invoiceNo];
        $this->invoices[$event->invoiceNo] = $invoice->addLateCharge($event->amount);
    }

    public function applyPaymentReceived(PaymentReceived $event)
    {
        $this->payments[$event->referenceNo] = PaymentState::create(
            paymentNo: $event->referenceNo,
            amount: $event->amount,
            occurredAt: $event->occurredAt,
        );
    }

    public function applyPaymentAllocated(PaymentAllocated $event)
    {
        $invoice = $this->invoices[$event->invoiceNo];
        $this->invoices[$event->invoiceNo] = $invoice->applyPayment(
            component: $event->component,
            amount: $event->amount,
        );

        $this->allocations[] = new AllocationLedgerEntry(
            id: $event->allocationId,
            sequence: $this->nextAllocationSequence(),
            occurredAt: $event->occurredAt,
            sourceType: AccountAllocationSourceTypeEnum::PAYMENT->value,
            sourceNo: $event->referenceNo,
            invoiceNo: $event->invoiceNo,
            component: $event->component,
            amount: $event->amount,
        );

        $this->paymentAllocationBalances[$event->allocationId] = $event->amount;

        $this->assertStateConsistency();
    }

    public function applyOverpaymentAllocated(OverpaymentAllocated $event)
    {
        $overpayment = $this->availableOverpayments[$event->referenceNo];
        $overpayment = $overpayment->consume($event->amount);
        $this->availableOverpayments[$event->referenceNo] = $overpayment;
        if ($overpayment->isNoRemaining()) {
            unset($this->availableOverpayments[$event->referenceNo]);
        }

        $invoice = $this->invoices[$event->invoiceNo];
        $this->invoices[$event->invoiceNo] = $invoice->applyPayment(
            component: $event->component,
            amount: $event->amount,
        );

        $this->allocations[] = new AllocationLedgerEntry(
            id: $event->allocationId,
            sequence: $this->nextAllocationSequence(),
            occurredAt: $event->occurredAt,
            sourceType: AccountAllocationSourceTypeEnum::OVERPAYMENT->value,
            sourceNo: $event->referenceNo,
            invoiceNo: $event->invoiceNo,
            component: $event->component,
            amount: $event->amount,
        );

        $this->paymentAllocationBalances[$event->allocationId] = $event->amount;

        $this->assertStateConsistency();
    }

    public function applyOverpaymentCreated(OverpaymentCreated $event)
    {
        $this->availableOverpayments[$event->referenceNo] = OverpaymentState::create(
            paymentNo: $event->referenceNo,
            remaining: $event->amount,
            occurredAt: $event->occurredAt,
        );

    }

    public function applyCreditNoteIssued(CreditNoteIssued $event)
    {
        $this->processedCreditNoteReferences[$event->referenceNo] = true;
        $this->availableCredits[$event->referenceNo] = CreditNoteState::create(
            creditNoteNo: $event->referenceNo,
            remaining: $event->amount,
            occurredAt: $event->occurredAt,
        );
    }

    public function applyCreditNoteAllocated(CreditNoteAllocated $event)
    {
        $invoice = $this->invoices[$event->invoiceNo];
        $this->invoices[$event->invoiceNo] = $invoice->applyPayment(
            component: $event->component,
            amount: $event->amount,
        );

        if (! isset($this->availableCredits[$event->referenceNo])) {
            throw new Exception('Credit note not found yet in allocation');
        }

        $credit = $this->availableCredits[$event->referenceNo];
        $credit = $credit->consume($event->amount);
        $this->availableCredits[$event->referenceNo] = $credit;

        if ($credit->isNoRemaining()) {
            unset($this->availableCredits[$event->referenceNo]);
        }

        $this->allocations[] = new AllocationLedgerEntry(
            id: $event->allocationId,
            sequence: $this->nextAllocationSequence(),
            occurredAt: $event->occurredAt,
            sourceType: AccountAllocationSourceTypeEnum::CREDIT_NOTE->value,
            sourceNo: $event->referenceNo,
            invoiceNo: $event->invoiceNo,
            component: $event->component,
            amount: $event->amount,
        );

        $this->creditAllocationBalances[$event->allocationId] = $event->amount;

        $this->assertStateConsistency();
    }

    public function applyRefundIssued(RefundIssued $event)
    {
        $this->processedRefundReferences[$event->referenceNo] = true;
    }

    public function applyPaymentAllocationReversed(PaymentAllocationReversed $event)
    {
        $invoiceNo = $event->invoiceNo;
        if (! isset($this->invoices[$invoiceNo])) {
            throw new Exception("Invoice {$invoiceNo} not found");
        }
        $invoice = $this->invoices[$invoiceNo];
        $this->invoices[$invoiceNo] = $invoice->reversePayment($event->component, $event->amount);

        $this->allocations[] = new AllocationLedgerEntry(
            id: $event->id,
            sequence: $this->nextAllocationSequence(),
            occurredAt: $event->occurredAt,
            sourceType: AccountAllocationSourceTypeEnum::PAYMENT_REVERSAL->value,
            sourceNo: $event->referenceNo,
            invoiceNo: $event->invoiceNo,
            component: $event->component,
            amount: $event->amount,
            allocationId: $event->allocationId
        );

        if (! isset($this->paymentAllocationBalances[$event->allocationId])) {
            throw new Exception('Allocation balance not found');
        }

        $this->paymentAllocationBalances[$event->allocationId] -= $event->amount;
        if ($this->paymentAllocationBalances[$event->allocationId] < 0) {
            throw new Exception('Allocation balance became negative');
        }

        $this->assertStateConsistency();
    }

    public function applyOverpaymentRefunded(OverpaymentRefunded $event)
    {
        if (! isset($this->availableOverpayments[$event->paymentNo])) {
            return;
        }

        $overpayment = $this->availableOverpayments[$event->paymentNo];
        $overpayment = $overpayment->consume($event->amount);
        $this->availableOverpayments[$event->paymentNo] = $overpayment;
        if ($overpayment->isNoRemaining()) {
            unset($this->availableOverpayments[$event->paymentNo]);
        }

        $this->assertStateConsistency();
    }

    public function applyCreditNoteAllocationReversed(CreditNoteAllocationReversed $event)
    {
        if (! isset($this->invoices[$event->invoiceNo])) {
            throw new Exception("Invoice {$event->invoiceNo} not found");
        }

        $invoice = $this->invoices[$event->invoiceNo];

        $this->invoices[$event->invoiceNo] = $invoice->reversePayment(component: $event->component, amount: $event->amount);

        if (! isset($this->availableCredits[$event->referenceNo])) {
            $this->availableCredits[$event->referenceNo] = CreditNoteState::create(
                creditNoteNo: $event->referenceNo,
                remaining: 0,
                occurredAt: $event->occurredAt,
            );
        }
        $credit = $this->availableCredits[$event->referenceNo];
        $this->availableCredits[$event->referenceNo] = $credit->refund($event->amount);

        $this->allocations[] = new AllocationLedgerEntry(
            id: $event->id,
            sequence: $this->nextAllocationSequence(),
            occurredAt: $event->occurredAt,
            sourceType: AccountAllocationSourceTypeEnum::CREDIT_NOTE_REVERSAL->value,
            sourceNo: $event->referenceNo,
            invoiceNo: $event->invoiceNo,
            component: $event->component,
            amount: $event->amount,
            allocationId: $event->allocationId,
        );

        if (! isset($this->creditAllocationBalances[$event->allocationId])) {
            throw new Exception('Allocation balance not found');
        }

        $this->creditAllocationBalances[$event->allocationId] -= $event->amount;
        if ($this->creditAllocationBalances[$event->allocationId] < 0) {
            throw new Exception('Allocation balance became negative');
        }

        $this->assertStateConsistency();
    }

    public function applyCreditNoteVoided(CreditNoteVoided $event)
    {
        $this->voidedCreditNotes[$event->referenceNo] = true;
        unset($this->availableCredits[$event->referenceNo]);
        $this->assertStateConsistency();
    }

    /** helpers functions */
    protected function allocatePaymentToInvoices(string $paymentNo, int $amount, string $occurredAt)
    {
        $result = $this->paymentAllocator->allocate($this->invoices, $amount);

        foreach ($result['allocations'] as $allocation) {
            $this->recordThat(new PaymentAllocated(
                accountId: $this->uuid(),
                referenceNo: $paymentNo,
                invoiceNo: $allocation['invoiceNo'],
                allocationId: (string) Str::uuid(),
                component: $allocation['component'],
                amount: $allocation['amount'],
                occurredAt: $occurredAt,
            ));
        }

        // overpayment
        if ($result['remaining'] > 0) {
            $this->recordThat(new OverpaymentCreated(
                accountId: $this->uuid(),
                referenceNo: $paymentNo,
                amount: $result['remaining'],
                occurredAt: $occurredAt,
            ));
        }
    }

    protected function allocateOverpaymentToInvoice(string $invoiceNo, int $amount, string $component, ?string $occurredAt = null)
    {
        $result = $this->overpaymentAllocator->allocate(
            overpayments: $this->availableOverpayments,
            invoiceNo: $invoiceNo,
            amount: $amount,
            component: $component
        );

        foreach ($result['allocations'] as $allocation) {
            $this->recordThat(new OverpaymentAllocated(
                accountId: $this->uuid(),
                referenceNo: $allocation['sourceNo'],
                invoiceNo: $invoiceNo,
                amount: $allocation['amount'],
                component: $component,
                allocationId: (string) Str::uuid(),
                occurredAt: $occurredAt,
            ));
        }

        return $result['remaining'];
    }

    protected function allocateCreditNoteToInvoice(string $invoiceNo, int $amount, string $component, ?string $occurredAt = null)
    {
        $result = $this->creditNoteAllocator->allocate(
            credits: $this->availableCredits,
            invoiceNo: $invoiceNo,
            amount: $amount,
            component: $component
        );

        foreach ($result['allocations'] as $allocation) {
            $this->recordThat(new CreditNoteAllocated(
                accountId: $this->uuid(),
                invoiceNo: $invoiceNo,
                amount: $allocation['amount'],
                component: $component,
                referenceNo: $allocation['sourceNo'],
                allocationId: (string) Str::uuid(),
                occurredAt: $occurredAt,
            ));
        }

        return $result['remaining'];
    }

    protected function refundOverpayments(string $referenceNo, int $amount)
    {
        $result = $this->overpaymentAllocator->reverse($this->availableOverpayments, $amount);

        foreach ($result['allocations'] as $allocation) {
            $this->recordThat(new OverpaymentRefunded(
                accountId: $this->uuid(),
                paymentNo: $allocation['sourceNo'],
                referenceNo: $referenceNo,
                amount: $allocation['amount'],
            ));
        }

        return $result['remaining'];
    }

    protected function reversePaymentAllocations(string $referenceNo, int $amount, string $occurredAt)
    {
        $result = $this->refundAllocator->allocate(
            allocations: $this->allocations,
            allocationBalances: $this->paymentAllocationBalances,
            amount: $amount,
        );

        foreach ($result['refunds'] as $refunded) {
            $this->recordThat(new PaymentAllocationReversed(
                accountId: $this->uuid(),
                allocationId: $refunded['id'],
                paymentNo: $refunded['sourceNo'],
                referenceNo: $referenceNo,
                invoiceNo: $refunded['invoiceNo'],
                component: $refunded['component'],
                amount: $refunded['amount'],
                id: (string) Str::uuid(),
                occurredAt: $occurredAt,
            ));
        }

        if ($result['remaining'] > 0) {
            throw new Exception('Refund allocation incomplete');
        }
    }

    protected function allocateCreditNoteToInvoices(string $referenceNo, int $amount, string $occurredAt)
    {
        $result = $this->creditNoteAllocator->allocateAll($this->invoices, $amount);

        foreach ($result['allocations'] as $allocation) {

            if ($allocation['amount'] <= 0) {
                continue;
            }

            $this->recordThat(new CreditNoteAllocated(
                accountId: $this->uuid(),
                referenceNo: $referenceNo,
                invoiceNo: $allocation['invoiceNo'],
                allocationId: (string) Str::uuid(),
                component: $allocation['component'],
                amount: $allocation['amount'],
                occurredAt: $occurredAt,
            ));
        }

        // balance CN do nothing
    }

    protected function nextAllocationSequence(): int
    {
        return ++$this->allocationSequence;
    }

    protected function calculateRefundableBalance(): int
    {
        $availableOverpayments = collect($this->availableOverpayments)
            ->sum('remaining');

        $reversibleAllocations = collect($this->allocations)
            ->filter(fn ($x) => in_array(
                $x->sourceType,
                [
                    AccountAllocationSourceTypeEnum::PAYMENT->value,
                    AccountAllocationSourceTypeEnum::OVERPAYMENT->value,
                ]
            ))
            ->sum(fn ($x) => $this->paymentAllocationBalances[$x->id] ?? 0);

        return $availableOverpayments + $reversibleAllocations;
    }

    /** development consistency check */
    protected function assertStateConsistency(): void
    {
        if (app()->isProduction()) {
            return;
        }

        foreach ($this->invoices as $invoice) {
            if ($invoice->principalPaid > $invoice->principalAmount) {
                throw new Exception("Invoice principal overpaid: {$invoice->invoiceNo}");
            } if ($invoice->lateChargePaid > $invoice->lateChargeAmount) {
                throw new Exception("Invoice late charge overpaid: {$invoice->invoiceNo}");
            } if ($invoice->principalPaid < 0) {
                throw new Exception("Invoice principal negative: {$invoice->invoiceNo}");
            } if ($invoice->lateChargePaid < 0) {
                throw new Exception("Invoice late charge negative: {$invoice->invoiceNo}");
            }
        }
        foreach ($this->paymentAllocationBalances as $id => $balance) {
            if ($balance < 0) {
                throw new Exception("Negative allocation balance: {$id}");
            }
        }

        foreach ($this->creditAllocationBalances as $id => $balance) {
            if ($balance < 0) {
                throw new Exception("Negative allocation balance: {$id}");
            }
        }
    }

    public function getVersion(): int
    {
        return $this->aggregateVersion;
    }

    public function getInvoicesCount(): int
    {
        return count($this->invoices);
    }
}
