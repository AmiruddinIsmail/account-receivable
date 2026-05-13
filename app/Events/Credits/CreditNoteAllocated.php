<?php

namespace App\Events\Credits;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CreditNoteAllocated extends ShouldBeStored
{
    public function __construct(
        public string $accountId,
        public string $referenceNo,
        public string $invoiceNo,
        public int $amount,
        public string $component,
        public string $allocationId,
    ){}
}