<?php

namespace App\Events\Credits;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CreditNoteIssued extends ShouldBeStored
{
    public function __construct(
        public string $accountId,
        public string $referenceNo,
        public int $amount,
        public string $occuredAt,
        public ?string $invoiceNo = null,
    ){}
}