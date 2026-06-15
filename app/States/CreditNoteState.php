<?php

namespace App\States;

use Exception;

final class CreditNoteState
{
    public function __construct(
        public readonly string $creditNoteNo,
        public readonly int $remaining,
        public readonly string $occurredAt,
    ) {}

    public static function create(string $creditNoteNo, int $remaining, string $occurredAt)
    {
        return new self(
            creditNoteNo: $creditNoteNo,
            remaining: $remaining,
            occurredAt: $occurredAt,
        );
    }

    public function consume(int $amount)
    {
        if ($amount > $this->remaining) {
            throw new Exception('Credit note applied exceeds remaining amount');
        }

        return new self(
            creditNoteNo: $this->creditNoteNo,
            remaining: $this->remaining - $amount,
            occurredAt: $this->occurredAt,
        );
    }

    public function refund(int $amount)
    {
        return new self(
            creditNoteNo: $this->creditNoteNo,
            remaining: $this->remaining + $amount,
            occurredAt: $this->occurredAt,
        );
    }

    public function isNoRemaining(): bool
    {
        return $this->remaining <= 0;
    }
}
