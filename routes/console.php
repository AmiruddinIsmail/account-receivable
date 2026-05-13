<?php

use App\Aggregates\AccountAggregate;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Schedule::command('app:daily-spider-transaction-processor')->daily();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());

    // AccountAggregate::retrieve('A001')
    //     ->invoiceCreated('INV-001', '2026-01-01', 10000)
    //     ->persist();

    // AccountAggregate::retrieve('A001')
    //     ->paymentReceived('PAY-001', '2026-01-02', 10000)
    //     ->persist();        

    // // AccountAggregate::retrieve('A001')
    // //     ->lateChargeApplied('LAT-001', '2026-01-01', 4000, 'INV-001')
    // //     ->persist();

    // AccountAggregate::retrieve('A001')
    //     ->invoiceCreated('INV-002', '2026-02-01', 10000)
    //     ->persist();

    // AccountAggregate::retrieve('A001')
    //     ->invoiceCreated('INV-003', '2026-03-01', 10000)
    //     ->persist();        

    // AccountAggregate::retrieve('A001')
    //     ->lateChargeApplied('LAT-001', '2026-03-01', 4000, 'INV-003')
    //     ->persist();

    // AccountAggregate::retrieve('A001')
    //     ->paymentReceived('PAY-002', '2026-03-02', 10000)
    //     ->persist();

    // AccountAggregate::retrieve('A001')
    //     ->invoiceCreated('INV-004', '2026-04-01', 10000)
    //     ->persist();        

    // AccountAggregate::retrieve('A001')
    //     ->lateChargeApplied('LAT-002', '2026-04-01', 4000, 'INV-004')
    //     ->persist();        

    // AccountAggregate::retrieve('A001')
    //     ->paymentReceived('PAY-003', '2026-04-02', 14000)
    //     ->persist();

    // AccountAggregate::retrieve('A001')
    //     ->invoiceCreated('INV-005', '2026-04-01', 5000, 'Unlock Fee')
    //     ->persist(); 

    // AccountAggregate::retrieve('A001')
    //     ->invoiceCreated('INV-003', '2026-03-01', 10000)
    //     ->persist();

    // AccountAggregate::retrieve('A001')
    //     ->refundIssued('REF-001', '2026-03-06', 2000)
    //     ->persist();

    // AccountAggregate::retrieve('A001')
    //     ->refundIssued('REF-002', '2026-03-06', 6000)
    //     ->persist();    

    // AccountAggregate::retrieve('A001')
    //     ->refundIssued('REF-002', '2026-02-06', 4000)
    //     ->persist(); 
    
    // AccountAggregate::retrieve('A001')
    //     ->refundIssued('REF-003', '2026-02-06', 4000)
    //     ->persist();     

    // AccountAggregate::retrieve('A001')
    //     ->paymentReceived('PAY-002', '2026-02-07', 4000)
    //     ->persist();


    // AccountAggregate::retrieve('A001')
    //     ->invoiceCreated('INV-003', '2026-02-01', 10000);
        // ->persist();

    // AccountAggregate::retrieve('A001')
    //     ->paymentReceived('PAY-002', '2026-02-05', 16000)
    //     ->persist();

    // AccountAggregate::retrieve('A001')
    //     ->invoiceCreated('INV-003', '2026-03-01', 10000)
    //     ->persist();

    // AccountAggregate::retrieve('A001')
    //     ->creditNoteIssued('CN-001', '2026-03-02', 4000, 'INV-003')
    //     ->persist();

    // AccountAggregate::retrieve('A001')
    //     ->invoiceCreated('INV-005', '2026-05-01', 10000)
    //     ->persist();    

})->purpose('Display an inspiring quote');
