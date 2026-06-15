<?php

namespace App\Events\Payments;

use App\Events\BaseAccountEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived extends BaseAccountEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        string $accountId,
        string $referenceNo,
        int $amount,
        string $occurredAt,
        public ?int $tenure = null,
    ) {
        parent::__construct($accountId, $referenceNo, $amount, $occurredAt);
    }
}
