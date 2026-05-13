<?php

namespace App\Projectors;

use App\Enums\AccountAllocationActionEnum;
use App\Events\Payments\OverpaymentAllocated;
use App\Events\Payments\PaymentAllocated;
use App\Events\Refunds\PaymentAllocationReversed;
use App\Models\AccountPaymentAllocation;
use Illuminate\Support\Carbon;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class PaymentAllocationProjector extends Projector
{
    public function onPaymentAllocated(PaymentAllocated $event)
    {
        AccountPaymentAllocation::create([
            'account_id' => $event->accountId,
            'reference_no' => $event->referenceNo,
            'invoice_no' => $event->invoiceNo,
            'component' => $event->component,
            'amount' => $event->amount,
            'action' => AccountAllocationActionEnum::ALLOCATE->value,
            'created_at' => Carbon::createFromFormat('Y-m-d', $event->occuredAt),
        ]);
    }

    public function onOverpaymentAllocated(OverpaymentAllocated $event)
    {
        AccountPaymentAllocation::create([
            'account_id' => $event->accountId,
            'reference_no' => $event->referenceNo,
            'invoice_no' => $event->invoiceNo,
            'component' => $event->component,
            'amount' => $event->amount,
            'action' => AccountAllocationActionEnum::ALLOCATE->value,
            'created_at' => Carbon::createFromFormat('Y-m-d', $event->occuredAt),
        ]);
    }

    public function onPaymentAllocationReversed(PaymentAllocationReversed $event)
    {
        AccountPaymentAllocation::create([
            'account_id' => $event->accountId,
            'reference_no' => $event->referenceNo,
            'invoice_no' => $event->invoiceNo,
            'component' => $event->component,
            'amount' => $event->amount,
            'action' => AccountAllocationActionEnum::REVERSE->value,
            'created_at' => Carbon::createFromFormat('Y-m-d', $event->occuredAt),
        ]);
    }
}
