<?php

use App\Aggregates\AccountAggregate;
use App\Models\AccountRefund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('projects refund events into the account_refunds table', function () {
    $accountId = (string) Str::uuid();
    $aggregate = AccountAggregate::retrieve($accountId);

    // 1. Create Invoice
    $aggregate->invoiceCreated(
        referenceNo: 'INV-001',
        occuredAt: '2024-01-01',
        amount: 1000
    )->persist();

    // 2. Receive Payment
    $aggregate->paymentReceived(
        referenceNo: 'PAY-001',
        occuredAt: '2024-01-05',
        amount: 1000
    )->persist();

    // 3. Issue Refund
    $aggregate->refundIssued(
        referenceNo: 'REF-001',
        occuredAt: '2024-01-20',
        amount: 250
    )->persist();

    $refunds = AccountRefund::where('account_id', $accountId)->get();
    expect($refunds)->toHaveCount(1);
    expect($refunds->first()->reference_no)->toBe('REF-001');
    expect($refunds->first()->amount)->toBe(250);
});
