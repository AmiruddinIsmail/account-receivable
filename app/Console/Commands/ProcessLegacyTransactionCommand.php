<?php

namespace App\Console\Commands;

use App\Externals\Spider\Actions\ProcessTransactionToTable;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProcessLegacyTransactionCommand extends Command
{
    protected $signature = 'app:spider-transaction-processor {--startDate=} {--endDate=}';

    protected $description = 'A command to fetch and process all spider transactions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $endedAt = today();
        $startedAt = today()->subDays(7);
        if ($this->option('startDate')) {
            $startedAt = Carbon::parse($this->option('startDate'));
        }

        if ($this->option('endDate')) {
            $endedAt = Carbon::parse($this->option('endDate'));
        }

        (new ProcessTransactionToTable)->handle($startedAt, $endedAt, $this->loggedResult(...), false);

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
