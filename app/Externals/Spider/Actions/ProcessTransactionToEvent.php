<?php

namespace App\Externals\Spider\Actions;

use App\Aggregates\AccountAggregate;
use App\Externals\Spider\Repositories\TransactionRepository;
use Illuminate\Support\Facades\Log;

class ProcessTransactionToEvent
{
    public function handle(string $startDate, string $endDate)
    {
        $repository = new TransactionRepository();
        $transactions = $repository->getTransactions($startDate, $endDate);
        $batchCount = 0;
        $aggregates = [];
        foreach ($transactions as $transaction) {
            try {
                $this->processTransactionRow($transaction, $aggregates);
                $batchCount++;
                if ($batchCount >= 1000) {
                    $this->persistAggregates($aggregates);
                    $aggregates = [];
                    $batchCount = 0;
                }
            } catch (\Exception $e) {
                Log::error("\nError processing row " . json_encode($transaction) . ": " . $e->getMessage());
            }
        }
        $this->persistAggregates($aggregates);
    }

    protected function processTransactionRow($row, array &$aggregates)
    {
        $mandate = $row->mandate ?? null;
        if (!$mandate) return;

        $type = $row->type ?? null;
        $date = $row->date ?? null;
        $referenceNo = $row->reference_no ?? null;
        $amount = $row['amount'] ?? 0;
        $amountCents = (int) round(((float) $amount) * 100);

        if (!isset($aggregates[$mandate])) {
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