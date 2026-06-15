<?php

namespace App\Projectors;

use App\Events\Credits\CreditNoteIssued;
use App\Models\AccountCredit;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class CreditNoteProjector extends Projector
{
    public function onCreditNoteIssued(CreditNoteIssued $event)
    {
        AccountCredit::create([
            'account_id' => $event->accountId,
            'reference_no' => $event->referenceNo,
            'occurred_at' => $event->occurredAt,
            'amount' => $event->amount,
            'tenure' => $event->tenure,
        ]);
    }
}
