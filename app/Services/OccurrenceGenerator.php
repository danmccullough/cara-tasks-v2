<?php

namespace App\Services;

use App\Enums\OccurrenceStatus;
use App\Models\Assignment;
use Carbon\CarbonImmutable;

/**
 * Turns an assignment's "these weekdays at this time" into occurrence rows
 * for the next few days. Safe to run repeatedly.
 */
class OccurrenceGenerator
{
    /**
     * Make the assignment's future pending occurrences match its current settings.
     * Past, submitted and approved occurrences are never touched.
     */
    public function sync(Assignment $assignment): void
    {
        $dueTimes = $assignment->active ? $this->dueTimes($assignment) : [];

        $assignment->occurrences()
            ->where('status', OccurrenceStatus::Pending)
            ->where('due_at', '>', now())
            ->whereNotIn('due_at', $dueTimes)
            ->delete();

        $now = now();

        $assignment->occurrences()->insertOrIgnore(array_map(fn (string $dueAt) => [
            'assignment_id' => $assignment->id,
            'chore_id' => $assignment->chore_id,
            'kid_id' => $assignment->kid_id,
            'due_at' => $dueAt,
            'status' => OccurrenceStatus::Pending->value,
            'created_at' => $now,
            'updated_at' => $now,
        ], $dueTimes));
    }

    /**
     * Upcoming due times in UTC ('Y-m-d H:i:s'), from today through the configured window.
     *
     * @return list<string>
     */
    private function dueTimes(Assignment $assignment): array
    {
        $tz = config('chores.timezone');
        $today = CarbonImmutable::now($tz)->startOfDay();
        $lastDay = $today->addDays(config('chores.generate_days_ahead'));
        $start = CarbonImmutable::parse($assignment->start_date->toDateString(), $tz);
        $end = $assignment->end_date ? CarbonImmutable::parse($assignment->end_date->toDateString(), $tz) : null;

        $times = [];

        for ($day = $today; $day->lte($lastDay); $day = $day->addDay()) {
            if ($day->lt($start) || ($end && $day->gt($end))) {
                continue;
            }

            if (! in_array($day->isoWeekday(), $assignment->days_of_week)) {
                continue;
            }

            $dueAt = CarbonImmutable::parse($day->toDateString().' '.$assignment->due_time, $tz);

            if ($dueAt->isPast()) {
                continue;
            }

            $times[] = $dueAt->utc()->format('Y-m-d H:i:s');
        }

        return $times;
    }
}
