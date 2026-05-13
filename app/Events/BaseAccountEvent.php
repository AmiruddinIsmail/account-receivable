<?php

namespace App\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

abstract class BaseAccountEvent extends ShouldBeStored
{
    public function __construct(
        public string $accountId,
        public string $referenceNo,
        public int $amount,
        public string $occuredAt,
    ){}
}