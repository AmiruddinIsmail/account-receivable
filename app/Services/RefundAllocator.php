<?php

namespace App\Services;

use App\Enums\AccountAllocationSourceTypeEnum;
use App\States\AllocationLedgerEntry;

final class RefundAllocator
{
    public function allocate(iterable $allocations, array $allocationBalances, int $amount)
    {
        $allocations = collect($allocations)
            ->filter(fn (AllocationLedgerEntry $x) => in_array($x->sourceType, [AccountAllocationSourceTypeEnum::PAYMENT->value, AccountAllocationSourceTypeEnum::OVERPAYMENT->value]))
            ->sortByDesc('sequence');

        $remaining = $amount;
        $refunds = [];

        foreach ($allocations as $allocation) {

            if ($remaining <= 0) {
                break;
            }

            $allocationId = $allocation->id;

            $available = $allocationBalances[$allocation->id] ?? 0;
            if ($available <= 0) {
                continue;
            }

            $reversal = min($remaining, $available);

            if ($reversal > 0) {
                $refunds[] = [
                    'id' => $allocationId,
                    'sourceNo' => $allocation->sourceNo,
                    'invoiceNo' => $allocation->invoiceNo,
                    'component' => $allocation->component,
                    'amount' => $reversal,
                ];

                $remaining -= $reversal;
            }
        }

        return [
            'remaining' => $remaining,
            'refunds' => $refunds,
        ];
    }
}
