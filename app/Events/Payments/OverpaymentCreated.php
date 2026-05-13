<?php

namespace App\Events\Payments;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OverpaymentCreated extends ShouldBeStored
{
    public function __construct(
        public string $accountId,
        public string $referenceNo,
        public int $amount,
        public string $occuredAt,
    ){}
}