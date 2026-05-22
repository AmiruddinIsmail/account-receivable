<?php

namespace App\Models;

use App\Enums\AccountInvoiceStatusEnum;
use Database\Factories\AccountInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountInvoice extends Model
{
    /** @use HasFactory<AccountInvoiceFactory> */
    use HasFactory;

    protected $guarded = [];

    /* |-------------------------------------------------------------------------- | Apply Payments |-------------------------------------------------------------------------- */
    public function resolvedPrincipalPaid(int $amount): void
    {
        $this->principal_paid_amt += $amount;
        $this->syncStatuses();
    }

    public function resolvedLateChargePaid(int $amount): void
    {
        $this->late_charge_paid_amt += $amount;
        $this->syncStatuses();
    }

    /* |-------------------------------------------------------------------------- | Reverse Payments |-------------------------------------------------------------------------- */

    public function subPrincipalPaid(int $amount): void
    {
        $this->principal_paid_amt -= $amount;
        if ($this->principal_paid_amt < 0) {
            $this->principal_paid_amt = 0;
        } $this->syncStatuses();
    }

    public function subLateChargePaid(int $amount): void
    {
        $this->late_charge_paid_amt -= $amount;
        if ($this->late_charge_paid_amt < 0) {
            $this->late_charge_paid_amt = 0;
        } $this->syncStatuses();
    }

    /* |-------------------------------------------------------------------------- | Balance Helpers |-------------------------------------------------------------------------- */

    public function checkBalance(): int
    {
        return ($this->principal_billed_amt + $this->late_charge_billed_amt) - ($this->principal_paid_amt + $this->late_charge_paid_amt);
    }

    public function checkPrincipalBalance(): int
    {
        return $this->principal_billed_amt - $this->principal_paid_amt;
    }

    public function checkLateChargeBalance(): int
    {
        return $this->late_charge_billed_amt - $this->late_charge_paid_amt;
    }

    /* |-------------------------------------------------------------------------- | Internal State Sync |-------------------------------------------------------------------------- */

    protected function syncStatuses(): void
    {
        $this->principal_status = $this->checkPrincipalBalance() <= 0 ? AccountInvoiceStatusEnum::CLOSED->value : AccountInvoiceStatusEnum::OPEN->value;
        $this->late_charge_status = $this->checkLateChargeBalance() <= 0 ? AccountInvoiceStatusEnum::CLOSED->value : AccountInvoiceStatusEnum::OPEN->value;
        $this->status = $this->checkBalance() <= 0 ? AccountInvoiceStatusEnum::CLOSED->value : AccountInvoiceStatusEnum::OPEN->value;
        $this->save();
    }
}
