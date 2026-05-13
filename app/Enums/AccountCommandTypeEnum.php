<?php

namespace App\Enums;

enum AccountCommandTypeEnum : string
{
    case INVOICE = 'Invoice';
    case LATE_CHARGE = 'Late Charge';
    case PAYMENT = 'Payment';
    case CREDIT_NOTE = 'Credit Note';
    case REFUND = 'Refund';
}
