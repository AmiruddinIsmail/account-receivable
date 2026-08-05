<?php

namespace App\Console\Commands;

use App\Externals\Spider\Actions\ProcessPaymentToEvent;
use App\Notifications\NotifyDailyTransactionCompleted;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\MicrosoftTeams\MicrosoftTeamsChannel;

class DailySpiderPaymentProcessor extends Command
{
    protected $signature = 'app:daily-spider-payment-processor {--startDate=} {--endDate=}';

    protected $description = 'A command to fetch and process all spider payments';

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

        (new ProcessPaymentToEvent)->handle($startedAt, $endedAt, $this->loggedResult(...));

        $this->info('Spider payments processed successfully from '.$startedAt.' to '.$endedAt);

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
