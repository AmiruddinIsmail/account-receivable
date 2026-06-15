<?php

namespace App\States;

final class AllocationLedgerEntry
{
    public function __construct(
        public readonly string $id,
        public readonly int $sequence,
        public readonly string $occurredAt,
        public readonly string $sourceType,
        public readonly string $sourceNo,
        public readonly string $invoiceNo,
        public readonly string $component,
        public readonly int $amount,
        public readonly ?string $allocationId = null,
    ) {}
}
