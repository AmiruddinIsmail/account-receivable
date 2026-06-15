<?php

namespace App\Models;

use Database\Factories\AccountPaymentAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountPaymentAllocation extends Model
{
    /** @use HasFactory<AccountPaymentAllocationFactory> */
    use HasFactory;

    protected $guarded = [];
}
