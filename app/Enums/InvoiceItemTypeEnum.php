<?php

namespace App\Enums;

enum InvoiceItemTypeEnum: string
{
    case PRINCIPAL = 'principal';
    case LATE_CHARGE = 'late-charge';
    case TAX = 'tax';
    case REACTIVATION_FEE = 'reactivation-fee';
    case RETURN_CHARGE_FEE = 'return-charge-fee';
}
