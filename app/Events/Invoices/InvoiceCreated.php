<?php

namespace App\Events\Invoices;

use App\Events\BaseAccountEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceCreated extends BaseAccountEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        string $accountId,
        string $referenceNo,
        int $amount,
        string $occurredAt,
        public string $type,
        public ?int $tenure = null,
        public ?int $subscriptionAmt = null,

    ) {
        parent::__construct($accountId, $referenceNo, $amount, $occurredAt);
    }

}
