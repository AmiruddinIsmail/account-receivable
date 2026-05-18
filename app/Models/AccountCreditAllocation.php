<?php

namespace App\Models;

use Database\Factories\AccountCreditAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountCreditAllocation extends Model
{
    /** @use HasFactory<AccountCreditAllocationFactory> */
    use HasFactory;

    protected $guarded = [];
}
