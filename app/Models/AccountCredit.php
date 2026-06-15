<?php

namespace App\Models;

use Database\Factories\AccountCreditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountCredit extends Model
{
    /** @use HasFactory<AccountCreditFactory> */
    use HasFactory;

    protected $guarded = [];
}
