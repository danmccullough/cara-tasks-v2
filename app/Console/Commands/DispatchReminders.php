<?php

namespace App\Console\Commands;

use App\Enums\OccurrenceStatus;
use App\Models\Occurrence;
use App\Models\User;
use App\Notifications\ChoreOverdue;
use App\Notifications\ChoreReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class DispatchReminders extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Send first reminders and follow-up nags for pending chores';

    /** Upper bound on remind_before_min (see AssignmentController validation). */
    private const MAX_LEAD_MIN = 240;

    public function handle(): int
    {
        $now = now();

        Occurrence::with(['assignment', 'chore', 'kid'])
            ->where('status', OccurrenceStatus::Pending)
            ->where('due_at', '<=', $now->copy()->addMinutes(self::MAX_LEAD_MIN))
            ->each(function (Occurrence $occurrence) use ($now) {
                $assignment = $occurrence->assignment;

                $due = $occurrence->reminders_sent === 0
                    ? $now->gte($occurrence->due_at->copy()->subMinutes($assignment->remind_before_min))
                    : $occurrence->reminders_sent <= $assignment->max_nags
                        && $now->gte($occurrence->last_reminded_at->copy()->addMinutes($assignment->nag_every_min));

                if (! $due) {
                    return;
                }

                $occurrence->kid->notify(new ChoreReminder($occurrence, isNag: $occurrence->reminders_sent > 0));

                $occurrence->update([
                    'reminders_sent' => $occurrence->reminders_sent + 1,
                    'last_reminded_at' => $now,
                ]);
            });

        $this->alertParentsOfOverdue($now);

        return self::SUCCESS;
    }

    /** Once per chore: tell parents when the due time passes and it isn't done. */
    private function alertParentsOfOverdue(Carbon $now): void
    {
        $parents = User::parents()->get();

        Occurrence::with(['chore', 'kid'])
            ->where('status', OccurrenceStatus::Pending)
            ->where('due_at', '<=', $now)
            ->whereNull('parents_alerted_at')
            ->each(function (Occurrence $occurrence) use ($parents, $now) {
                Notification::send($parents, new ChoreOverdue($occurrence));

                $occurrence->update(['parents_alerted_at' => $now]);
            });
    }
}
