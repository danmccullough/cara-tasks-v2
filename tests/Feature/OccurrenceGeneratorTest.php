<?php

namespace Tests\Feature;

use App\Enums\OccurrenceStatus;
use App\Models\Assignment;
use App\Models\Occurrence;
use App\Services\OccurrenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OccurrenceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['chores.timezone' => 'America/New_York', 'chores.generate_days_ahead' => 7]);
    }

    private function dueTimes(Assignment $assignment): array
    {
        return $assignment->occurrences()->orderBy('due_at')->get()
            ->map(fn ($o) => $o->due_at->utc()->format('Y-m-d H:i'))
            ->all();
    }

    public function test_daily_assignment_generates_one_per_day_in_household_time()
    {
        // Monday 2026-10-12, 08:00 New York (12:00 UTC).
        $this->travelTo('2026-10-12 12:00:00');
        $assignment = Assignment::factory()->create(['due_time' => '19:00', 'start_date' => '2026-10-12']);

        app(OccurrenceGenerator::class)->sync($assignment);

        // 7 days ahead from today inclusive = 8 days (12th..19th), 19:00 EDT = 23:00 UTC.
        $this->assertSame([
            '2026-10-12 23:00', '2026-10-13 23:00', '2026-10-14 23:00', '2026-10-15 23:00',
            '2026-10-16 23:00', '2026-10-17 23:00', '2026-10-18 23:00', '2026-10-19 23:00',
        ], $this->dueTimes($assignment));
    }

    public function test_only_selected_weekdays_are_generated()
    {
        $this->travelTo('2026-10-12 12:00:00'); // Monday
        $assignment = Assignment::factory()->create(['days_of_week' => [1, 3, 5], 'due_time' => '07:30', 'start_date' => '2026-10-01']);

        app(OccurrenceGenerator::class)->sync($assignment);

        // Today's 07:30 has already passed, so it starts Wednesday.
        $this->assertSame(['2026-10-14 11:30', '2026-10-16 11:30', '2026-10-19 11:30'], $this->dueTimes($assignment));
    }

    public function test_due_time_stays_local_across_dst_change()
    {
        // US DST ends Sunday 2026-11-01.
        $this->travelTo('2026-10-30 12:00:00');
        $assignment = Assignment::factory()->create(['due_time' => '19:00', 'start_date' => '2026-10-30', 'end_date' => '2026-11-02']);

        app(OccurrenceGenerator::class)->sync($assignment);

        $this->assertSame([
            '2026-10-30 23:00', // EDT
            '2026-10-31 23:00', // EDT
            '2026-11-02 00:00', // Nov 1 19:00 EST
            '2026-11-03 00:00', // Nov 2 19:00 EST
        ], $this->dueTimes($assignment));
    }

    public function test_respects_start_and_end_dates_and_inactive()
    {
        $this->travelTo('2026-10-12 12:00:00');
        $future = Assignment::factory()->create(['start_date' => '2026-10-18']);
        $inactive = Assignment::factory()->create(['active' => false]);

        app(OccurrenceGenerator::class)->sync($future);
        app(OccurrenceGenerator::class)->sync($inactive);

        $this->assertSame(['2026-10-18 23:00', '2026-10-19 23:00'], $this->dueTimes($future));
        $this->assertSame([], $this->dueTimes($inactive));
    }

    public function test_sync_is_idempotent()
    {
        $this->travelTo('2026-10-12 12:00:00');
        $assignment = Assignment::factory()->create();

        app(OccurrenceGenerator::class)->sync($assignment);
        app(OccurrenceGenerator::class)->sync($assignment);

        $this->assertSame(8, $assignment->occurrences()->count());
    }

    public function test_editing_replaces_future_pending_but_keeps_history_and_done_items()
    {
        $this->travelTo('2026-10-12 12:00:00');
        $assignment = Assignment::factory()->create(['due_time' => '19:00']);
        $past = Occurrence::factory()->forAssignment($assignment)->create(['due_at' => '2026-10-11 23:00:00']);
        $done = Occurrence::factory()->forAssignment($assignment)->submitted()->create(['due_at' => '2026-10-12 23:00:00']);

        $assignment->update(['due_time' => '20:00']);
        app(OccurrenceGenerator::class)->sync($assignment);

        $this->assertModelExists($past);
        $this->assertModelExists($done);
        $this->assertSame(OccurrenceStatus::Submitted, $done->fresh()->status);
        $this->assertSame(0, $assignment->occurrences()->where('status', 'pending')
            ->where('due_at', '>', now())->whereTime('due_at', '23:00:00')->count());
        $this->assertSame(8, $assignment->occurrences()->where('status', 'pending')
            ->where('due_at', '>', now())->whereTime('due_at', '00:00:00')->count());
    }

    public function test_deactivating_removes_future_pending()
    {
        $this->travelTo('2026-10-12 12:00:00');
        $assignment = Assignment::factory()->create();
        app(OccurrenceGenerator::class)->sync($assignment);

        $assignment->update(['active' => false]);
        app(OccurrenceGenerator::class)->sync($assignment);

        $this->assertSame(0, $assignment->occurrences()->count());
    }

    public function test_daily_command_generates_for_all_active_assignments()
    {
        $this->travelTo('2026-10-12 12:00:00');
        Assignment::factory()->count(2)->create();
        Assignment::factory()->create(['active' => false]);

        $this->artisan('occurrences:generate')->assertSuccessful();

        $this->assertSame(16, Occurrence::count());
    }
}
