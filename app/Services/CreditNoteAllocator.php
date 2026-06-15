<?php

namespace App\Services;

use App\Enums\AccountAllocationComponentEnum;
use App\Enums\AccountAllocationSourceTypeEnum;
use App\States\AllocationLedgerEntry;
use App\States\InvoiceState;

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

            if ($apply > 0) {
                $allocations[] = [
                    'sourceNo' => $credit->creditNoteNo,
                    'invoiceNo' => $invoiceNo,
                    'component' => $component,
                    'amount' => $apply,
                ];

                $remaining -= $apply;
            }
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

    public function allocateAll(iterable $invoices, int $amount): array
    {
        $remaining = $amount;
        $allocations = [];
        $sorted = collect($invoices)
            ->filter(fn (InvoiceState $x) => ! $x->isClosed())
            ->sortBy([
                ['occurredAt', 'asc'],
                ['invoiceNo', 'asc'],
            ]);

        foreach ($sorted as $invoice) {

            if ($remaining <= 0) {
                break;
            }

            $toPayPrincipal = min($invoice->principalBalance(), $remaining);
            if ($toPayPrincipal > 0) {
                $allocations[] = [
                    'invoiceNo' => $invoice->invoiceNo,
                    'component' => AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value,
                    'amount' => $toPayPrincipal,
                ];

                $remaining -= $toPayPrincipal;
            }

            $toPayLateCharge = min($invoice->lateChargeBalance(), $remaining);
            if ($toPayLateCharge > 0) {
                $allocations[] = [
                    'invoiceNo' => $invoice->invoiceNo,
                    'component' => AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value,
                    'amount' => $toPayLateCharge,
                ];

                $remaining -= $toPayLateCharge;
            }
        }

        return [
            'allocations' => $allocations,
            'remaining' => $remaining,
        ];
    }
}
