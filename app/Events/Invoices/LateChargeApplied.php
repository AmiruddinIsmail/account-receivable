<?php

namespace App\Events\Invoices;

use App\Events\BaseAccountEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LateChargeApplied extends BaseAccountEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        string $accountId,
        string $referenceNo,
        int $amount,
        string $occuredAt,
        public string $invoiceNo,
    ) {        
        parent::__construct($accountId, $referenceNo, $amount, $occuredAt);
    }

}
