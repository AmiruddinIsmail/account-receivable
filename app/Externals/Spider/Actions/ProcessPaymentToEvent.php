<?php

namespace App\Externals\Spider\Actions;

use App\Aggregates\AccountAggregate;
use App\Externals\Spider\Repositories\PaymentRepository;

final class ProcessPaymentToEvent
{
    public function handle(string $startDate, string $endDate, ?callable $output)
    {
        $output && $output('info', 'Processing Spider payments from '.$startDate.' to '.$endDate.'...');

        $repository = new PaymentRepository;

        $transactions = $repository->getPayments($startDate, $endDate);

        $batchCount = 0;
        $totalProcessed = 0;
        $aggregates = [];
        foreach ($transactions as $transaction) {
            try {
                $this->processTransactionRow($transaction, $aggregates);
                $output && $output('info', 'processing row: '.$transaction->reference_no);
                $batchCount++;
                $totalProcessed++;
                if ($batchCount >= 200) {
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
                if (! in_array($e->getMessage(), ['Duplicate invoice', 'Duplicate payment', 'Duplicate late charge', 'Duplicate credit note', 'Duplicate refund'])) {
                    $output && $output('error', $errorMsg);
                }
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
        $amountCents = (int) round(((float) $row->amount ?? 0) * 100);
        $tenure = $row->tenure ?? null;

        if (! isset($aggregates[$mandate])) {
            $aggregates[$mandate] = AccountAggregate::retrieve($mandate);
        }

        $aggregate = $aggregates[$mandate];

        switch ($type) {
            case 'payment':
                $aggregate->paymentReceived(
                    referenceNo: $referenceNo,
                    occurredAt: $date,
                    amount: $amountCents,
                    tenure: $tenure,
                );
                break;
            case 'refund':
                $aggregate->refundIssued(
                    referenceNo: $referenceNo,
                    occurredAt: $date,
                    amount: $amountCents,
                    tenure: $tenure,
                );
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
