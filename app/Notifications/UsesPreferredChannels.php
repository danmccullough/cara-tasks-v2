<?php

namespace App\Notifications;

use App\Models\User;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Sends on whichever channels the user has turned on (users.notify_channels).
 */
trait UsesPreferredChannels
{
    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        $map = ['database' => 'database', 'webpush' => WebPushChannel::class];

        return array_values(array_map(fn ($key) => $map[$key], array_intersect($notifiable->notificationChannels(), array_keys($map))));
    }
}
