<?php

namespace App\Console\Commands;

use App\Externals\Spider\Actions\ProcessTransactionToEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:daily-spider-transaction-processor')]
#[Description('A command to fetch and process all spider transactions')]
class DailySpiderTransactionProcessor extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $endedAt = today();
        $startedAt = today()->subDays(7);

        (new ProcessTransactionToEvent())->handle($startedAt, $endedAt);

        $this->info('Spider transactions processed successfully from ' . $startedAt->toDateTimeString() . ' to ' . $endedAt->toDateTimeString());

        return 0;
    }
}
