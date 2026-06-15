<?php

namespace App\Console\Commands;

use App\Externals\Spider\Actions\ProcessTransactionToEvent;
use App\Notifications\NotifyDailyTransactionCompleted;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\MicrosoftTeams\MicrosoftTeamsChannel;

class DailySpiderTransactionProcessor extends Command
{
    protected $signature = 'app:daily-spider-transaction-processor {--import-historical : Mute projectors for fast mass import} {--startDate=} {--endDate=}';

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

        $historical = $this->option('import-historical');

        (new ProcessTransactionToEvent)->handle($startedAt, $endedAt, $this->loggedResult(...), $historical);

        $this->info('Spider transactions processed successfully from '.$startedAt.' to '.$endedAt);

        Notification::route(MicrosoftTeamsChannel::class, null)->notify(new NotifyDailyTransactionCompleted);

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
