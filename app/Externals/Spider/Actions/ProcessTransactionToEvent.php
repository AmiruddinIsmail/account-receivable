<?php

namespace App\Externals\Spider\Actions;

use App\Aggregates\AccountAggregate;
use App\Externals\Spider\Repositories\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\EventSourcing\Facades\Projectionist;

class ProcessTransactionToEvent
{
    public function handle(string $startDate, string $endDate, ?callable $output = null, bool $muteProjectors = false)
    {
        DB::disableQueryLog();

        if ($muteProjectors) {
            $output && $output('info', 'NOTICE: Muting all projectors for fast historical import. Run `php artisan event-sourcing:replay` afterwards.');
            Projectionist::withoutEventHandlers();
        }

        $repository = new TransactionRepository;
        $transactions = $repository->getTransactions($startDate, $endDate);
        $batchCount = 0;
        $totalProcessed = 0;
        $aggregates = [];
        foreach ($transactions as $transaction) {
            try {
                $this->processTransactionRow($transaction, $aggregates);
                $batchCount++;
                $totalProcessed++;
                if ($batchCount >= 1000) {
                    $output && $output('info', "Processed {$totalProcessed} transactions. Persisting batch (Current Date: {$transaction->date_at})...");
                    $this->persistAggregates($aggregates);
                    $aggregates = [];
                    $batchCount = 0;

                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                }
            } catch (\Exception $e) {
                $errorMsg = 'Error processing row '.json_encode($transaction).': '.$e->getMessage();
                Log::error("\n".$errorMsg);
                $output && $output('error', $errorMsg);
            }
        }
        if (! empty($aggregates)) {
            $output && $output('info', 'Persisting final batch of '.count($aggregates)." aggregates (Total processed: {$totalProcessed})...");
            $this->persistAggregates($aggregates);
        }
    }

    protected function processTransactionRow($row, array &$aggregates)
    {
        $mandate = $row->mandate ?? null;
        if (! $mandate) {
            return;
        }

        $type = $row->type ?? null;
        $date = $row->date_at ?? null;
        $referenceNo = $row->reference_no ?? null;
        $amount = $row->amount ?? 0;
        $amountCents = (int) round(((float) $amount) * 100);

        if (! isset($aggregates[$mandate])) {
            $aggregates[$mandate] = AccountAggregate::retrieve($mandate);
        }

        $aggregate = $aggregates[$mandate];

        switch ($type) {
            case 'invoice':
                $aggregate->invoiceCreated($referenceNo, $date, $amountCents);
                break;
            case 'payment':
                $aggregate->paymentReceived($referenceNo, $date, $amountCents);
                break;
            case 'lpc':
                $invoiceNo = str_replace('LATE-', '', $referenceNo);
                $aggregate->lateChargeApplied($referenceNo, $date, $amountCents, $invoiceNo);
                break;
            case 'cn':
                $aggregate->creditNoteIssued($referenceNo, $date, $amountCents);
                break;
            case 'refund':
                $aggregate->refundIssued($referenceNo, $date, $amountCents);
                break;
            default:
                break;
        }
    }

    protected function persistAggregates(array $aggregates)
    {
        foreach ($aggregates as $aggregate) {
            $aggregate->persist();
        }
    }
}
