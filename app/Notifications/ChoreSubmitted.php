<?php

namespace App\Notifications;

use App\Models\Occurrence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/** To parents: a kid marked a chore done and it needs approval. */
class ChoreSubmitted extends Notification implements ShouldQueue
{
    use Queueable, UsesPreferredChannels;

    public function __construct(public Occurrence $occurrence) {}

    private function text(): string
    {
        return "{$this->occurrence->kid->name} finished: {$this->occurrence->chore->title}";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'occurrence_id' => $this->occurrence->id,
            'title' => 'Ready for approval',
            'body' => $this->text(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('✅ Ready for approval')
            ->body($this->text())
            ->icon('/icons/icon-192.png')
            ->data(['url' => '/dashboard']);
    }
}
