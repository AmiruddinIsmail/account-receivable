<?php

namespace App\Enums;

enum AccountAllocationSourceTypeEnum: string
{
    case PAYMENT = 'payment';
    case CREDIT_NOTE = 'credit-note';
    case OVERPAYMENT = 'overpayment';
    case PAYMENT_REVERSAL = 'payment-reversal';
    case CREDIT_NOTE_REVERSAL = 'credit-note-reversal';
}
