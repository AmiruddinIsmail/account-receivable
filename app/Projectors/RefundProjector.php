<?php

namespace App\Projectors;

use App\Events\Refunds\RefundIssued;
use App\Models\AccountRefund;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class RefundProjector extends Projector
{
    public function onRefundIssued(RefundIssued $event)
    {
        AccountRefund::create([
            'account_id' => $event->accountId,
            'reference_no' => $event->referenceNo,
            'occured_at' => $event->occuredAt,
            'amount' => $event->amount,
        ]);
    }
}
