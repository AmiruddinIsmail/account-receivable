<?php

namespace App\Events\Refunds;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class PaymentAllocationReversed extends ShouldBeStored
{
    public function __construct(
        public string $accountId,
        public string $referenceNo,
        public string $allocationId,
        public string $paymentNo,
        public string $invoiceNo,
        public int $amount,
        public string $component,
        public string $id,
        public string $occuredAt,
    ){}
}