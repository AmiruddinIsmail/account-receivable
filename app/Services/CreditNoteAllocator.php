<?php

namespace App\Services;

use App\Enums\AccountAllocationSourceTypeEnum;
use App\States\AllocationLedgerEntry;

final class CreditNoteAllocator
{
    public function allocate(iterable $credits, string $invoiceNo, int $amount, string $component)
    {
        $remaining = $amount;
        $allocations = [];

        $sorted = collect($credits)
            ->sortBy([
                ['occurredAt', 'asc'],
                ['creditNoteNo', 'asc'],
            ]);

        foreach ($sorted as $credit) {

            if ($remaining <= 0) {
                break;
            }

            $apply = min($remaining, $credit->remaining);

            $allocations[] = [
                'sourceNo' => $credit->creditNoteNo,
                'invoiceNo' => $invoiceNo,
                'component' => $component,
                'amount' => $apply,
            ];

            $remaining -= $apply;
        }

        return [
            'remaining' => $remaining,
            'allocations' => $allocations,
        ];
    }

    public function reverse(iterable $allocations, array $allocationBalances, string $referenceNo): array
    {
        $reversals = [];

        /** * Reverse latest allocations first (LIFO) */
        $creditAllocations = collect($allocations)
            ->filter(fn (AllocationLedgerEntry $x) => $x->sourceType === AccountAllocationSourceTypeEnum::CREDIT_NOTE->value
                && $x->sourceNo === $referenceNo
            )
            ->sortByDesc('sequence');

        foreach ($creditAllocations as $allocation) {

            $remaining = $allocationBalances[$allocation->id] ?? 0;
            if ($remaining <= 0) {
                continue;
            }

            $reversals[] = [
                'allocationId' => $allocation->id,
                'invoiceNo' => $allocation->invoiceNo,
                'component' => $allocation->component,
                'amount' => $remaining,
            ];
        }

        return $reversals;
    }
}
