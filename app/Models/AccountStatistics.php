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
        'is_delinquent' => 'boolean',
        'last_payment_at' => 'datetime',
        'last_invoice_at' => 'datetime',
        'last_event_at' => 'datetime',
    ];
}
