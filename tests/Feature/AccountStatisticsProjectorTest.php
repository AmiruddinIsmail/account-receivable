<?php

use App\Aggregates\AccountAggregate;
use App\Models\AccountStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('updates account statistics when events are recorded', function () {
    $accountId = (string) Str::uuid();
    $aggregate = AccountAggregate::retrieve($accountId);

    // 1. Create Invoice
    $aggregate->invoiceCreated(
        referenceNo: 'INV-001',
        occuredAt: '2024-01-01',
        amount: 1000
    )->persist();

    $stats = AccountStatistics::find($accountId);
    expect($stats->invoices_count)->toBe(1);
    expect($stats->total_invoices_amt)->toBe(1000);
    expect($stats->remaining_balance_amt)->toBe(1000);

    // 2. Receive Payment
    $aggregate->paymentReceived(
        referenceNo: 'PAY-001',
        occuredAt: '2024-01-05',
        amount: 600
    )->persist();

    $stats->refresh();
    expect($stats->total_payments_amt)->toBe(600);
    expect($stats->remaining_balance_amt)->toBe(400); // 1000 - 600

    // 3. Apply Late Charge
    $aggregate->lateChargeApplied(
        referenceNo: 'LPC-001',
        occuredAt: '2024-01-10',
        amount: 50,
        invoiceNo: 'INV-001'
    )->persist();

    $stats->refresh();
    expect($stats->total_late_charge_billed_amt)->toBe(50);
    expect($stats->remaining_balance_amt)->toBe(450);

    // 4. Issue Credit Note
    $aggregate->creditNoteIssued(
        referenceNo: 'CN-001',
        occuredAt: '2024-01-15',
        amount: 100,
        invoiceNo: 'INV-001'
    )->persist();

    $stats->refresh();
    expect($stats->total_credits_amt)->toBe(100);
    expect($stats->remaining_balance_amt)->toBe(350);

    // 5. Refund Issued (Part of payment)
    $aggregate->refundIssued(
        referenceNo: 'REF-001',
        occuredAt: '2024-01-20',
        amount: 200
    )->persist();

    $stats->refresh();
    expect($stats->total_refunded_amt)->toBe(200);
    expect($stats->remaining_balance_amt)->toBe(550); // 350 + 200
});
