<?php

namespace App\Services;

final class OverpaymentAllocator
{
    public function allocate(iterable $overpayments, string $invoiceNo, int $amount, string $component)
    {
        $remaining = $amount;
        $allocations = [];

        $sorted = collect($overpayments)
            ->sortBy([
                ['occurredAt', 'asc'],
                ['paymentNo', 'asc'],
            ]);

        foreach ($sorted as $item) {

            if ($remaining <= 0) {
                break;
            }

            $apply = min($remaining, $item->remaining);

            if ($apply > 0) {
                $allocations[] = [
                    'sourceNo' => $item->paymentNo,
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

    public function reverse(iterable $overpayments, int $amount)
    {
        $remaining = $amount;
        $allocations = [];

        $sorted = collect($overpayments)
            ->sortBy([
                ['occurredAt', 'desc'],
                ['paymentNo', 'desc'],
            ]);
        foreach ($sorted as $item) {

            if ($remaining <= 0) {
                break;
            }

            $consume = min($remaining, $item->remaining);

            if ($consume > 0) {
                $allocations[] = [
                    'sourceNo' => $item->paymentNo,
                    'amount' => $consume,
                ];

                $remaining -= $consume;
            }
        }

        return [
            'remaining' => $remaining,
            'allocations' => $allocations,
        ];
    }
}
