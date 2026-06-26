<?php

namespace App\Enums;

enum InvoiceItemComponentEnum: string
{
    case PRINCIPAL = 'principal';
    case LATE_CHARGE = 'late-charge';
    case OTHERS = 'others';

}
