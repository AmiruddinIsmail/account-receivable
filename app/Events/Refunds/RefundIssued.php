<?php

namespace App\Events\Refunds;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class RefundIssued extends ShouldBeStored
{
    public function __construct(
        public string $accountId,
        public string $referenceNo,
        public int $amount,
        public string $occuredAt,        
    ){}
}