<?php

namespace App\Projectors;

use App\Events\Payments\PaymentReceived;
use App\Models\AccountPayment;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class PaymentProjector extends Projector
{
    public function onPaymentReceived(PaymentReceived $event)
    {
        AccountPayment::create([
            'account_id' => $event->accountId,
            'reference_no' => $event->referenceNo,
            'occurred_at' => $event->occurredAt,
            'amount' => $event->amount,
            'notes' => $event->notes ?? null,
            'tenure' => $event->tenure,
        ]);
    }
}
