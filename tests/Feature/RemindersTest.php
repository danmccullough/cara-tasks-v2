<?php

namespace Tests\Feature;

use App\Enums\OccurrenceStatus;
use App\Models\Assignment;
use App\Models\Occurrence;
use App\Models\User;
use App\Notifications\ChoreMissed;
use App\Notifications\ChoreOverdue;
use App\Notifications\ChoreReminder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RemindersTest extends TestCase
{
    use RefreshDatabase;

    private User $kid;

    private Occurrence $occurrence;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config(['chores.grace_min' => 120]);
        $this->travelTo('2026-10-12 22:00:00');

        $this->kid = User::factory()->kid()->create();
        $assignment = Assignment::factory()->create([
            'kid_id' => $this->kid->id,
            'remind_before_min' => 15,
            'nag_every_min' => 10,
            'max_nags' => 2,
        ]);
        // Due 23:00, so first reminder at 22:45, nags at 22:55 and 23:05.
        $this->occurrence = Occurrence::factory()->forAssignment($assignment)->create(['due_at' => '2026-10-12 23:00:00']);
    }

    private function runAt(string $time): void
    {
        $this->travelTo($time);
        $this->artisan('reminders:dispatch')->assertSuccessful();
    }

    public function test_no_reminder_before_the_lead_time()
    {
        $this->runAt('2026-10-12 22:44:00');

        Notification::assertNothingSent();
    }

    public function test_first_reminder_then_nags_spaced_out_then_stop()
    {
        $this->runAt('2026-10-12 22:45:00');
        Notification::assertSentToTimes($this->kid, ChoreReminder::class, 1);

        $this->runAt('2026-10-12 22:50:00'); // too soon for a nag
        Notification::assertSentToTimes($this->kid, ChoreReminder::class, 1);

        $this->runAt('2026-10-12 22:55:00');
        $this->runAt('2026-10-12 23:05:00');
        Notification::assertSentToTimes($this->kid, ChoreReminder::class, 3);

        $this->runAt('2026-10-12 23:15:00'); // max_nags reached
        $this->runAt('2026-10-12 23:45:00');
        Notification::assertSentToTimes($this->kid, ChoreReminder::class, 3);

        $this->assertSame(3, $this->occurrence->fresh()->reminders_sent);
    }

    public function test_nag_notifications_are_marked_as_nags()
    {
        $this->runAt('2026-10-12 22:45:00');
        $this->runAt('2026-10-12 22:55:00');

        $sent = Notification::sent($this->kid, ChoreReminder::class);
        $this->assertFalse($sent[0]->isNag);
        $this->assertTrue($sent[1]->isNag);
    }

    public function test_reminders_stop_once_submitted()
    {
        $this->runAt('2026-10-12 22:45:00');
        $this->occurrence->update(['status' => OccurrenceStatus::Submitted]);

        $this->runAt('2026-10-12 22:55:00');

        Notification::assertSentToTimes($this->kid, ChoreReminder::class, 1);
    }

    public function test_rejection_restarts_reminders()
    {
        $parent = User::factory()->create();
        $this->occurrence->update(['status' => OccurrenceStatus::Submitted, 'reminders_sent' => 3, 'last_reminded_at' => now()]);

        $this->travelTo('2026-10-12 23:20:00');
        $this->actingAs($parent)->post("/occurrences/{$this->occurrence->id}/reject");
        $this->runAt('2026-10-12 23:20:00');

        Notification::assertSentToTimes($this->kid, ChoreReminder::class, 1);
    }

    public function test_parents_are_alerted_once_when_a_chore_is_not_done_by_its_due_time()
    {
        $parents = User::factory()->count(2)->create();

        $this->runAt('2026-10-12 22:59:00');
        Notification::assertNothingSentTo($parents[0]);

        $this->runAt('2026-10-12 23:00:00');
        $this->runAt('2026-10-12 23:10:00');

        Notification::assertSentToTimes($parents[0], ChoreOverdue::class, 1);
        Notification::assertSentToTimes($parents[1], ChoreOverdue::class, 1);
        Notification::assertNotSentTo($this->kid, ChoreOverdue::class);
    }

    public function test_no_overdue_alert_when_done_on_time()
    {
        $parent = User::factory()->create();
        $this->occurrence->update(['status' => OccurrenceStatus::Submitted]);

        $this->runAt('2026-10-12 23:00:00');

        Notification::assertNotSentTo($parent, ChoreOverdue::class);
    }

    public function test_pending_chore_past_grace_is_marked_missed_and_parents_told_once()
    {
        $parents = User::factory()->count(2)->create();

        $this->travelTo('2026-10-13 00:59:00');
        $this->artisan('chores:mark-missed')->assertSuccessful();
        $this->assertSame(OccurrenceStatus::Pending, $this->occurrence->fresh()->status);

        $this->travelTo('2026-10-13 01:00:00');
        $this->artisan('chores:mark-missed')->assertSuccessful();
        $this->artisan('chores:mark-missed')->assertSuccessful();

        $this->assertSame(OccurrenceStatus::Missed, $this->occurrence->fresh()->status);
        Notification::assertSentToTimes($parents[0], ChoreMissed::class, 1);
        Notification::assertSentToTimes($parents[1], ChoreMissed::class, 1);
        Notification::assertNotSentTo($this->kid, ChoreMissed::class);
    }

    public function test_no_reminders_for_missed_chores()
    {
        $this->occurrence->update(['status' => OccurrenceStatus::Missed]);

        $this->runAt('2026-10-12 22:45:00');

        Notification::assertNothingSent();
    }

    public function test_commands_are_scheduled()
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode(' ');

        $this->assertStringContainsString('reminders:dispatch', $commands);
        $this->assertStringContainsString('chores:mark-missed', $commands);
        $this->assertStringContainsString('occurrences:generate', $commands);
    }
}
