<?php

namespace App\Services;

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
}
