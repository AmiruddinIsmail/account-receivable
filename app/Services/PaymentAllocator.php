<?php

namespace App\Services;

use App\Enums\AccountAllocationComponentEnum;
use App\States\InvoiceState;

final class PaymentAllocator
{
    public function allocate(iterable $invoices, int $amount): array
    {
        $remaining = $amount;
        $allocations = [];
        $sorted = collect($invoices)
            ->filter(fn (InvoiceState $x) => ! $x->isClosed())
            ->sortBy([
                ['occurredAt', 'asc'],
                ['invoiceNo', 'asc'],
            ]);

        /* PASS 1 - PRINCIPAL */
        foreach ($sorted as $invoice) {

            if ($remaining <= 0) {
                break;
            }

            $balance = $invoice->principalBalance();
            if ($balance <= 0) {
                continue;
            }

            $pay = min($balance, $remaining);
            $allocations[] = [
                'invoiceNo' => $invoice->invoiceNo,
                'component' => AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value,
                'amount' => $pay,
            ];

            $remaining -= $pay;
        }

        /* PASS 2 - LATE CHARGE */
        foreach ($sorted as $invoice) {

            if ($remaining <= 0) {
                break;
            }

            $balance = $invoice->lateChargeBalance();

            if ($balance <= 0) {
                continue;
            }

            $pay = min($balance, $remaining);
            $allocations[] = [
                'invoiceNo' => $invoice->invoiceNo,
                'component' => AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value,
                'amount' => $pay,
            ];

            $remaining -= $pay;
        }

        return [
            'allocations' => $allocations,
            'remaining' => $remaining,
        ];
    }
}
