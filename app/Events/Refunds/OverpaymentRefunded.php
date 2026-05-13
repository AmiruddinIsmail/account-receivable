<?php

namespace App\Events\Refunds;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OverpaymentRefunded extends ShouldBeStored
{
    public function __construct(
        public string $accountId,
        public string $referenceNo,
        public string $paymentNo,
        public int $amount,
    ){}
}