<?php

namespace App\Notifications;

use App\Models\Occurrence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/** To parents only: a chore was never marked done. */
class ChoreMissed extends Notification implements ShouldQueue
{
    use Queueable, UsesPreferredChannels;

    public function __construct(public Occurrence $occurrence) {}

    private function text(): string
    {
        return "{$this->occurrence->kid->name} didn't get to: {$this->occurrence->chore->title}";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'occurrence_id' => $this->occurrence->id,
            'title' => 'Missed chore',
            'body' => $this->text(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Missed chore')
            ->body($this->text())
            ->icon('/icons/icon-192.png')
            ->data(['url' => '/dashboard']);
    }
}
