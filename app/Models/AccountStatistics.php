<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountStatistics extends Model
{
    protected $primaryKey = 'account_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'total_principal_billed_amt' => 'integer',
        'total_late_charge_billed_amt' => 'integer',
        'total_invoices_amt' => 'integer',
        'total_payments_amt' => 'integer',
        'total_refunded_amt' => 'integer',
        'total_credit_notes_amt' => 'integer',
        'total_allocated_principal_amt' => 'integer',
        'total_allocated_late_charge_amt' => 'integer',
        'total_allocated_payments_amt' => 'integer',
        'total_allocated_credits_amt' => 'integer',
        'remaining_principal_amt' => 'integer',
        'remaining_late_charge_amt' => 'integer',
        'remaining_balance_amt' => 'integer',
        'unallocated_overpayment_amt' => 'integer',
        'is_delinquent' => 'boolean',
        'collection_rate' => 'float',
        'last_payment_at' => 'datetime',
        'last_invoice_at' => 'datetime',
        'last_event_at' => 'datetime',
    ];
}
