<?php

namespace App\Models;

use Database\Factories\AccountPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountPayment extends Model
{
    /** @use HasFactory<AccountPaymentFactory> */
    use HasFactory;

    protected $guarded = [];
}
