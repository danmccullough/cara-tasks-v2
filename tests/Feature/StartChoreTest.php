<?php

namespace Tests\Feature;

use App\Enums\OccurrenceStatus;
use App\Models\Assignment;
use App\Models\Occurrence;
use App\Models\User;
use App\Notifications\ChoreOverdue;
use App\Notifications\ChoreReminder;
use App\Notifications\ChoreStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StartChoreTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    private User $kid;

    private Occurrence $occurrence;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo('2026-10-12 22:45:00');

        $this->parent = User::factory()->create();
        $this->kid = User::factory()->kid()->create();
        $assignment = Assignment::factory()->create([
            'kid_id' => $this->kid->id,
            'remind_before_min' => 15,
            'nag_every_min' => 10,
            'max_nags' => 2,
        ]);
        $this->occurrence = Occurrence::factory()->forAssignment($assignment)->create(['due_at' => '2026-10-12 23:00:00']);
    }

    private function runRemindersAt(string $time): void
    {
        $this->travelTo($time);
        $this->artisan('reminders:dispatch')->assertSuccessful();
    }

    public function test_kid_can_start_a_chore_and_parents_are_told()
    {
        $this->actingAs($this->kid)->post("/occurrences/{$this->occurrence->id}/start")->assertRedirect();

        $occurrence = $this->occurrence->fresh();
        $this->assertSame(OccurrenceStatus::Pending, $occurrence->status);
        $this->assertEquals(now(), $occurrence->started_at);
        Notification::assertSentTo($this->parent, ChoreStarted::class);
        Notification::assertNotSentTo($this->kid, ChoreStarted::class);
    }

    public function test_cannot_start_twice_after_submitting_or_a_siblings_chore()
    {
        $this->actingAs($this->kid)->post("/occurrences/{$this->occurrence->id}/start");
        $this->actingAs($this->kid)->post("/occurrences/{$this->occurrence->id}/start")->assertStatus(409);

        $submitted = Occurrence::factory()->forAssignment($this->occurrence->assignment)->submitted()->create(['due_at' => '2026-10-13 23:00:00']);
        $this->actingAs($this->kid)->post("/occurrences/{$submitted->id}/start")->assertStatus(409);

        $sibling = Occurrence::factory()->create();
        $this->actingAs($this->kid)->post("/occurrences/{$sibling->id}/start")->assertForbidden();
    }

    public function test_starting_is_optional()
    {
        $this->actingAs($this->kid)->post("/occurrences/{$this->occurrence->id}/submit")->assertRedirect();

        $this->assertSame(OccurrenceStatus::Submitted, $this->occurrence->fresh()->status);
    }

    public function test_starting_pauses_nags_then_asks_if_finished_with_a_fresh_nag_budget()
    {
        $this->runRemindersAt('2026-10-12 22:45:00'); // first reminder
        $this->runRemindersAt('2026-10-12 22:55:00'); // nag 1

        $this->travelTo('2026-10-12 22:58:00');
        $this->actingAs($this->kid)->post("/occurrences/{$this->occurrence->id}/start");

        $this->runRemindersAt('2026-10-12 23:05:00'); // only 7 min since start: no nag
        Notification::assertSentToTimes($this->kid, ChoreReminder::class, 2);

        $this->runRemindersAt('2026-10-12 23:08:00');
        $this->runRemindersAt('2026-10-12 23:18:00');
        $this->runRemindersAt('2026-10-12 23:28:00'); // budget used up
        Notification::assertSentToTimes($this->kid, ChoreReminder::class, 4);

        $sent = Notification::sent($this->kid, ChoreReminder::class);
        $this->assertTrue($sent[2]->isNag);
        $this->assertStringContainsString('Did you finish', $sent[2]->toArray($this->kid)['body']);
    }

    public function test_due_time_alert_to_parents_says_it_was_started()
    {
        $this->actingAs($this->kid)->post("/occurrences/{$this->occurrence->id}/start");

        $this->runRemindersAt('2026-10-12 23:00:00');

        Notification::assertSentTo($this->parent, ChoreOverdue::class, function (ChoreOverdue $n) {
            return str_contains($n->toArray($this->parent)['body'], 'started');
        });
    }

    public function test_sending_back_clears_started()
    {
        $this->occurrence->update(['started_at' => now(), 'status' => OccurrenceStatus::Submitted]);

        $this->actingAs($this->parent)->post("/occurrences/{$this->occurrence->id}/reject");

        $this->assertNull($this->occurrence->fresh()->started_at);
    }

    public function test_started_time_is_shown_to_kid_and_parent()
    {
        config(['chores.timezone' => 'UTC']);
        $this->occurrence->update(['started_at' => now()]);

        $this->actingAs($this->kid)->get('/today')
            ->assertInertia(fn ($page) => $page->where('occurrences.0.started_at', now()->toIso8601String()));
        $this->actingAs($this->parent)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('kids.0.today.0.started_at', now()->toIso8601String()));
    }
}
