<?php

namespace App\States;

final class PaymentState
{
    public function __construct(
        public readonly string $paymentNo,
        public readonly int $amount,
        public readonly string $occurredAt,
    ) {}

    public static function create(string $paymentNo, int $amount, string $occurredAt)
    {
        return new self(
            paymentNo: $paymentNo,
            amount: $amount,
            occurredAt: $occurredAt,
        );
    }
}
