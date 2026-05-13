<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountPayment extends Model
{
    /** @use HasFactory<\Database\Factories\AccountPaymentFactory> */
    use HasFactory;

    protected $guarded = [];
}
