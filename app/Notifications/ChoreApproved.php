<?php

namespace App\Notifications;

use App\Models\Occurrence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/** To the kid: a parent approved a chore. */
class ChoreApproved extends Notification implements ShouldQueue
{
    use Queueable, UsesPreferredChannels;

    public function __construct(public Occurrence $occurrence) {}

    public function toArray(object $notifiable): array
    {
        return [
            'occurrence_id' => $this->occurrence->id,
            'title' => $this->occurrence->chore->title,
            'body' => "+{$this->occurrence->chore->points} points!",
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        $chore = $this->occurrence->chore;

        return (new WebPushMessage)
            ->title("⭐ +{$chore->points} points!")
            ->body("Great job on: {$chore->title}")
            ->icon('/icons/icon-192.png')
            ->data(['url' => '/today']);
    }
}
