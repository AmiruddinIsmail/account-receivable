<?php

namespace App\Enums;

enum InvoiceTypeEnum: string
{
    case INVOICE = 'invoice';
    case UPGRADE_TERMINATE_FEE = 'upgrade/termination-fee';
    case REACTIVATION_FEE = 'reactivation';
}
