<?php

namespace App\Notifications;

use App\Models\Occurrence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/** To parents: the due time has passed and the kid hasn't tapped Done yet. */
class ChoreOverdue extends Notification implements ShouldQueue
{
    use Queueable, UsesPreferredChannels;

    public function __construct(public Occurrence $occurrence) {}

    private function text(): string
    {
        $name = $this->occurrence->kid->name;
        $chore = $this->occurrence->chore->title;

        return $this->occurrence->started_at
            ? "{$name} started {$chore} but hasn't finished yet"
            : "{$name} hasn't done {$chore} yet";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'occurrence_id' => $this->occurrence->id,
            'title' => 'Not done yet',
            'body' => $this->text(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title("⏰ {$this->occurrence->chore->icon} Not done yet")
            ->body($this->text())
            ->icon('/icons/icon-192.png')
            ->data(['url' => '/dashboard']);
    }
}
