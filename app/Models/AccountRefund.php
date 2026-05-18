<?php

namespace App\Models;

use Database\Factories\AccountRefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountRefund extends Model
{
    /** @use HasFactory<AccountRefundFactory> */
    use HasFactory;

    protected $guarded = [];
}
