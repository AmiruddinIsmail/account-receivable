<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\MicrosoftTeams\ContentBlocks\TextBlock;
use NotificationChannels\MicrosoftTeams\MicrosoftTeamsAdaptiveCard;
use NotificationChannels\MicrosoftTeams\MicrosoftTeamsChannel;

class NotifyDailyTransactionCompleted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [MicrosoftTeamsChannel::class];
    }

    public function toMicrosoftTeams($notifiable)
    {
        return MicrosoftTeamsAdaptiveCard::create()
            ->to(config('services.microsoft_teams.webhook_url'))
            ->title('Daily Transaction')
            ->content([
                TextBlock::create()->setText('Daily transaction completed'),
            ]);
    }
}
