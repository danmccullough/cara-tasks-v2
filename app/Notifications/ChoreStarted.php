<?php

namespace App\Notifications;

use App\Models\Occurrence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/** To parents: a kid tapped "Start" on a chore. */
class ChoreStarted extends Notification implements ShouldQueue
{
    use Queueable, UsesPreferredChannels;

    public function __construct(public Occurrence $occurrence) {}

    private function text(): string
    {
        return "{$this->occurrence->kid->name} started: {$this->occurrence->chore->title}";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'occurrence_id' => $this->occurrence->id,
            'title' => 'Chore started',
            'body' => $this->text(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title("▶️ {$this->occurrence->chore->icon} Chore started")
            ->body($this->text())
            ->icon('/icons/icon-192.png')
            ->data(['url' => '/dashboard']);
    }
}
