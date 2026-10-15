<?php

namespace Tests\Feature;

use App\Enums\OccurrenceStatus;
use App\Models\Assignment;
use App\Models\Occurrence;
use App\Models\User;
use App\Services\StreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreakServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $kid;

    private Assignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        config(['chores.timezone' => 'America/New_York']);
        $this->travelTo('2026-10-15 16:00:00'); // Thursday, 12:00 New York
        $this->kid = User::factory()->kid()->create();
        $this->assignment = Assignment::factory()->create(['kid_id' => $this->kid->id]);
    }

    /** Create an occurrence on the given New York date (default 19:00 local = 23:00 UTC). */
    private function on(string $date, OccurrenceStatus $status, string $utcTime = '23:00:00'): void
    {
        Occurrence::factory()->forAssignment($this->assignment)->create([
            'due_at' => $date.' '.$utcTime,
            'status' => $status,
        ]);
    }

    private function streak(): int
    {
        return app(StreakService::class)->current($this->kid);
    }

    public function test_no_history_means_no_streak()
    {
        $this->assertSame(0, $this->streak());
    }

    public function test_counts_consecutive_completed_days_back_from_yesterday()
    {
        $this->on('2026-10-11', OccurrenceStatus::Missed);
        $this->on('2026-10-12', OccurrenceStatus::Approved);
        $this->on('2026-10-13', OccurrenceStatus::Approved);
        $this->on('2026-10-14', OccurrenceStatus::Submitted); // awaiting review still counts

        $this->assertSame(3, $this->streak());
    }

    public function test_unfinished_today_does_not_break_the_streak_and_finished_today_adds_one()
    {
        $this->on('2026-10-14', OccurrenceStatus::Approved);
        $this->on('2026-10-15', OccurrenceStatus::Pending);
        $this->assertSame(1, $this->streak());

        Occurrence::where('status', 'pending')->update(['status' => 'submitted']);
        $this->assertSame(2, $this->streak());
    }

    public function test_one_missed_chore_breaks_the_day()
    {
        $this->on('2026-10-13', OccurrenceStatus::Approved);
        $this->on('2026-10-14', OccurrenceStatus::Approved);
        $this->on('2026-10-14', OccurrenceStatus::Missed, '12:00:00');

        $this->assertSame(0, $this->streak());
    }

    public function test_days_without_chores_are_skipped()
    {
        $this->on('2026-10-10', OccurrenceStatus::Approved);
        $this->on('2026-10-12', OccurrenceStatus::Approved); // nothing on the 11th, 13th or 14th

        $this->assertSame(2, $this->streak());
    }
}
