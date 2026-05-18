<?php

namespace App\Console\Commands;

use App\Externals\Spider\Actions\ProcessTransactionToEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:daily-spider-transaction-processor {--import-historical : Mute projectors for fast mass import}')]
#[Description('A command to fetch and process all spider transactions')]
class DailySpiderTransactionProcessor extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $endedAt = '2024-01-01'; // today();
        $startedAt = '2023-01-01'; // today()->subDays(7);
        $historical = $this->option('import-historical');

        (new ProcessTransactionToEvent)->handle($startedAt, $endedAt, $this->loggedResult(...), $historical);

        $this->info('Spider transactions processed successfully from '.$startedAt.' to '.$endedAt);

        return 0;
    }

    protected function loggedResult($type, $message)
    {
        if ($type === 'error') {
            $this->error($message);
        } else {
            $this->comment($message);
        }
    }
}
