<?php

namespace App\Console\Commands;

use App\Enums\OccurrenceStatus;
use App\Models\Occurrence;
use App\Models\User;
use App\Notifications\ChoreMissed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class MarkMissed extends Command
{
    protected $signature = 'chores:mark-missed';

    protected $description = 'Mark chores still pending after the grace period as missed and tell the parents';

    public function handle(): int
    {
        $cutoff = now()->subMinutes(config('chores.grace_min'));
        $parents = User::parents()->get();

        Occurrence::with(['chore', 'kid'])
            ->where('status', OccurrenceStatus::Pending)
            ->where('due_at', '<=', $cutoff)
            ->each(function (Occurrence $occurrence) use ($parents) {
                $occurrence->update(['status' => OccurrenceStatus::Missed]);

                Notification::send($parents, new ChoreMissed($occurrence));
            });

        return self::SUCCESS;
    }
}
