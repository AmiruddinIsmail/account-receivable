<?php

namespace App\Events\Payments;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PaymentAllocated extends ShouldBeStored
{
    public function __construct(
        public string $accountId,
        public string $referenceNo,
        public string $invoiceNo,
        public int $amount,
        public string $component,
        public string $allocationId,
        public string $occuredAt,
    ){}
}