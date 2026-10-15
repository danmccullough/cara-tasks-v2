<?php

namespace App\Notifications;

use App\Models\Occurrence;
use App\Support\Household;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/** To the kid: a chore is coming up (or still waiting, when isNag). */
class ChoreReminder extends Notification implements ShouldQueue
{
    use Queueable, UsesPreferredChannels;

    public function __construct(public Occurrence $occurrence, public bool $isNag = false) {}

    private function body(): string
    {
        $time = $this->occurrence->due_at->setTimezone(Household::timezone())->format('g:i A');

        if ($this->isNag && $this->occurrence->started_at) {
            return "Did you finish {$this->occurrence->chore->title}? Tap Done when you're finished.";
        }

        return $this->isNag
            ? "Still waiting for you! Tap Done when you've finished."
            : "Due at {$time}. Tap Done when you've finished.";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'occurrence_id' => $this->occurrence->id,
            'title' => $this->occurrence->chore->title,
            'body' => $this->body(),
        ];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        $chore = $this->occurrence->chore;

        return (new WebPushMessage)
            ->title("{$chore->icon} Time to: {$chore->title}")
            ->body($this->body())
            ->icon('/icons/icon-192.png')
            ->tag("occurrence-{$this->occurrence->id}") // nags replace the previous notification...
            ->renotify()                                 // ...but still buzz again
            ->requireInteraction()
            ->data(['url' => '/today']);
    }
}
