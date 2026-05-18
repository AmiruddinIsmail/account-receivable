<?php

namespace App\Models;

use Database\Factories\AccountInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountInvoice extends Model
{
    /** @use HasFactory<AccountInvoiceFactory> */
    use HasFactory;

    protected $guarded = [];

    public function resolvedPrincipalPaid(int $amount)
    {
        $this->principal_paid_amt += $amount;

        if ($this->checkPrincipalBalance() <= 0) {
            $this->principal_status = 'paid';
        }

        if ($this->checkBalance() <= 0) {
            $this->principal_status = 'paid';
            $this->late_charge_status = 'paid';
            $this->status = 'paid';
        }

        $this->save();
    }

    public function resolvedLateChargePaid(int $amount)
    {
        $this->late_charge_paid_amt += $amount;

        if ($this->checkLateChargeBalance() <= 0) {
            $this->late_charge_status = 'paid';
        }

        if ($this->checkBalance() <= 0) {
            $this->principal_status = 'paid';
            $this->late_charge_status = 'paid';
            $this->status = 'paid';
        }

        $this->save();
    }

    public function subPrincipalPaid(int $amount)
    {
        $this->principal_paid_amt -= $amount;
        if ($this->checkBalance() > 0) {
            $this->status = 'open';
        }

        $this->save();
    }

    public function subLateChargePaid(int $amount)
    {
        $this->late_charge_paid_amt -= $amount;
        if ($this->checkBalance() > 0) {
            $this->status = 'open';
        }

        $this->save();
    }

    public function checkBalance()
    {
        return ($this->principal_billed_amt + $this->late_charge_billed_amt) - ($this->principal_paid_amt + $this->late_charge_paid_amt);
    }

    public function checkPrincipalBalance()
    {
        return $this->principal_billed_amt - $this->principal_paid_amt;
    }

    public function checkLateChargeBalance()
    {
        return $this->late_charge_billed_amt - $this->late_charge_paid_amt;
    }
}
