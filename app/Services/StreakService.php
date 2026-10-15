<?php

namespace App\Services;

use App\Models\Occurrence;
use App\Models\User;
use App\Support\Household;

class StreakService
{
    private const LOOKBACK_DAYS = 365;

    /**
     * Consecutive days (in household time) on which every chore was done.
     * Today only adds to the streak once it is complete; it never breaks it.
     * Days with no chores are skipped.
     */
    public function current(User $kid): int
    {
        $today = Household::today();
        [$from] = Household::dayRange($today->subDays(self::LOOKBACK_DAYS));
        [, $to] = Household::dayRange($today);

        $days = $kid->occurrences()
            ->whereBetween('due_at', [$from, $to])
            ->get(['due_at', 'status'])
            ->groupBy(fn (Occurrence $o) => $o->due_at->setTimezone(Household::timezone())->toDateString())
            ->map(fn ($occurrences) => $occurrences->every(fn (Occurrence $o) => $o->status->isDone()));

        $streak = ($days[$today->toDateString()] ?? false) ? 1 : 0;

        for ($day = $today->subDay(); $day->gte($today->subDays(self::LOOKBACK_DAYS)); $day = $day->subDay()) {
            $complete = $days[$day->toDateString()] ?? null;

            if ($complete === null) {
                continue;
            }

            if (! $complete) {
                break;
            }

            $streak++;
        }

        return $streak;
    }
}
