<?php

namespace App\States;

use Exception;

final class OverpaymentState
{
    public function __construct(
        public readonly string $paymentNo,
        public readonly int $remaining,
        public readonly string $occurredAt,
    ) {}

    public static function create(string $paymentNo, int $remaining, string $occurredAt)
    {
        return new self(
            paymentNo: $paymentNo,
            remaining: $remaining,
            occurredAt: $occurredAt,
        );
    }

    public function consume(int $amount)
    {
        if ($amount > $this->remaining) {
            throw new Exception('Overpayment applied exceed remaining amount');
        }

        return new self(
            paymentNo: $this->paymentNo,
            remaining: $this->remaining - $amount,
            occurredAt: $this->occurredAt,
        );
    }

    public function refund(int $amount)
    {
        return new self(
            paymentNo: $this->paymentNo,
            remaining: $this->remaining + $amount,
            occurredAt: $this->occurredAt,
        );
    }

    public function isNoRemaining(): bool
    {
        return $this->remaining <= 0;
    }
}
