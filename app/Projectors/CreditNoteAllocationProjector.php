<?php

namespace App\Projectors;

use App\Enums\AccountAllocationActionEnum;
use App\Events\Credits\CreditNoteAllocated;
use App\Models\AccountCreditAllocation;
use Illuminate\Support\Carbon;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class CreditNoteAllocationProjector extends Projector
{
    public function onCreditNoteAllocated(CreditNoteAllocated $event)
    {
        AccountCreditAllocation::create([
            'account_id' => $event->accountId,
            'reference_no' => $event->referenceNo,
            'invoice_no' => $event->invoiceNo,
            'component' => $event->component,
            'amount' => $event->amount,
            'action' => AccountAllocationActionEnum::ALLOCATE->value,
            'created_at' => $event->occuredAt ? Carbon::createFromFormat('Y-m-d', $event->occuredAt) : now(),
        ]);
    }
}
