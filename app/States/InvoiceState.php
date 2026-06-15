<?php

namespace App\States;

use App\Enums\AccountAllocationComponentEnum;
use App\Enums\AccountInvoiceStatusEnum;
use Exception;

final class InvoiceState
{
    public function __construct(
        public readonly string $invoiceNo,
        public readonly string $occurredAt,
        public readonly int $principalAmount,
        public readonly int $lateChargeAmount,
        public readonly int $principalPaid,
        public readonly int $lateChargePaid,
    ) {}

    public static function create(string $invoiceNo, string $occurredAt, int $amount): self
    {
        return new self(
            invoiceNo: $invoiceNo,
            occurredAt: $occurredAt,
            principalAmount: $amount,
            lateChargeAmount: 0,
            principalPaid: 0,
            lateChargePaid: 0,
        );
    }

    public function addLateCharge(int $amount): self
    {
        return new self(
            invoiceNo: $this->invoiceNo,
            occurredAt: $this->occurredAt,
            principalAmount: $this->principalAmount,
            lateChargeAmount: $this->lateChargeAmount + $amount,
            principalPaid: $this->principalPaid,
            lateChargePaid: $this->lateChargePaid,
        );
    }

    public function applyPayment(string $component, int $amount): self
    {
        return match ($component) {
            AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value => new self(
                invoiceNo: $this->invoiceNo,
                occurredAt: $this->occurredAt,
                principalAmount: $this->principalAmount,
                lateChargeAmount: $this->lateChargeAmount,
                principalPaid: $this->principalPaid + $amount,
                lateChargePaid: $this->lateChargePaid,
            ),

            AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value => new self(
                invoiceNo: $this->invoiceNo,
                occurredAt: $this->occurredAt,
                principalAmount: $this->principalAmount,
                lateChargeAmount: $this->lateChargeAmount,
                principalPaid: $this->principalPaid,
                lateChargePaid: $this->lateChargePaid + $amount,
            ),

            default => throw new Exception('Invalid component'),
        };
    }

    public function reversePayment(string $component, int $amount): self
    {
        return match ($component) {
            AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value => $this->reversePrincipal($amount),
            AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value => $this->reverseLateCharge($amount),
            default => throw new Exception('Invalid component'),
        };
    }

    protected function reversePrincipal(int $amount): self
    {
        if ($amount > $this->principalPaid) {
            throw new Exception('Principal reversal exceeds paid');
        }

        return new self(
            invoiceNo: $this->invoiceNo,
            occurredAt: $this->occurredAt,
            principalAmount: $this->principalAmount,
            lateChargeAmount: $this->lateChargeAmount,
            principalPaid: $this->principalPaid - $amount,
            lateChargePaid: $this->lateChargePaid,
        );
    }

    protected function reverseLateCharge(int $amount): self
    {
        if ($amount > $this->lateChargePaid) {
            throw new Exception('Late charge reversal exceeds paid');
        }

        return new self(
            invoiceNo: $this->invoiceNo,
            occurredAt: $this->occurredAt,
            principalAmount: $this->principalAmount,
            lateChargeAmount: $this->lateChargeAmount,
            principalPaid: $this->principalPaid,
            lateChargePaid: $this->lateChargePaid - $amount,
        );
    }

    public function balance(): int
    {
        return ($this->principalAmount - $this->principalPaid) + ($this->lateChargeAmount - $this->lateChargePaid);
    }

    public function principalBalance(): int
    {
        return $this->principalAmount - $this->principalPaid;
    }

    public function lateChargeBalance(): int
    {
        return $this->lateChargeAmount - $this->lateChargePaid;
    }

    public function isClosed(): bool
    {
        return $this->balance() <= 0;
    }

    public function status(): string
    {
        return $this->isClosed() ? AccountInvoiceStatusEnum::CLOSED->value : AccountInvoiceStatusEnum::OPEN->value;
    }
}
