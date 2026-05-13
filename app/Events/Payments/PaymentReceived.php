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
        string $occuredAt,
    ) {
        parent::__construct($accountId, $referenceNo, $amount, $occuredAt);
    }
}
