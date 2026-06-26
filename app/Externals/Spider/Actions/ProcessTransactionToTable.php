<?php

namespace App\Externals\Spider\Actions;

use App\Externals\Spider\Repositories\TransactionRepository;
use App\Models\SpiderTransaction;
use Illuminate\Support\Facades\DB;

class ProcessTransactionToTable
{
    public function handle(string $startDate, string $endDate, ?callable $output = null, bool $muteProjectors = false)
    {
        DB::disableQueryLog();

        $repository = new TransactionRepository;
        $transactions = $repository->getTransactions($startDate, $endDate);
        $chunks = 1000;
        $data = [];
        $totalProcessed = 0;

        foreach ($transactions as $transaction) {
            $data[] = (array) $transaction;
            $totalProcessed++;
            if (count($data) >= $chunks) {
                $output && $output('info', "Processed {$totalProcessed} transactions. Persisting batch (Current Date: {$transaction->date_at})...");
                DB::transaction(function () use ($data) {
                    SpiderTransaction::insertOrIgnore($data);
                });
                $data = [];
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
        }
        if (! empty($data)) {
            $output && $output('info', 'Persisting final batch of '.count($data)." aggregates (Total processed: {$totalProcessed})...");
            DB::transaction(function () use ($data) {
                SpiderTransaction::insertOrIgnore($data);
            });
        }

    }
}
