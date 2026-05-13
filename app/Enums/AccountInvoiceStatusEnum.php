<?php

namespace App\Enums;

enum AccountInvoiceStatusEnum : string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case PARTIALLY_PAID = 'partialy-paid';
}
