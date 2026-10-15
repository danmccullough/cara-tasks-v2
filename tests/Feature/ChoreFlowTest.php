<?php

namespace Tests\Feature;

use App\Enums\OccurrenceStatus;
use App\Enums\PointReason;
use App\Models\Assignment;
use App\Models\Chore;
use App\Models\Occurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChoreFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    private User $kid;

    private Assignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        config(['chores.timezone' => 'America/New_York']);
        $this->travelTo('2026-10-12 16:00:00'); // Monday, 12:00 New York
        $this->parent = User::factory()->create();
        $this->kid = User::factory()->kid()->create();
        $this->assignment = Assignment::factory()->create([
            'kid_id' => $this->kid->id,
            'chore_id' => Chore::factory()->create(['title' => 'Shower', 'points' => 5]),
        ]);
    }

    private function occurrence(array $attrs = []): Occurrence
    {
        return Occurrence::factory()->forAssignment($this->assignment)->create($attrs);
    }

    public function test_today_shows_only_this_kids_chores_due_today_in_household_time()
    {
        $today = $this->occurrence(['due_at' => '2026-10-12 23:00:00']);        // 19:00 NY today
        $lateTonight = $this->occurrence(['due_at' => '2026-10-13 03:30:00']);  // 23:30 NY today
        $this->occurrence(['due_at' => '2026-10-13 23:00:00']);                 // tomorrow
        $this->occurrence(['due_at' => '2026-10-12 13:00:00', 'status' => OccurrenceStatus::Missed]); // hidden from kid
        Occurrence::factory()->create(['due_at' => '2026-10-12 23:00:00']);     // sibling's

        $this->actingAs($this->kid)->get('/today')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('kid/Today')
                ->has('occurrences', 2)
                ->where('occurrences.0.id', $today->id)
                ->where('occurrences.0.title', 'Shower')
                ->where('occurrences.1.id', $lateTonight->id)
                ->where('points', 0)
                ->where('streak', 0));
    }

    public function test_kid_can_submit_their_pending_chore()
    {
        $occurrence = $this->occurrence();

        $this->actingAs($this->kid)->post("/occurrences/{$occurrence->id}/submit")->assertRedirect();

        $occurrence->refresh();
        $this->assertSame(OccurrenceStatus::Submitted, $occurrence->status);
        $this->assertNotNull($occurrence->submitted_at);
    }

    public function test_kid_cannot_submit_a_siblings_chore()
    {
        $sibling = Occurrence::factory()->create();

        $this->actingAs($this->kid)->post("/occurrences/{$sibling->id}/submit")->assertForbidden();
        $this->assertSame(OccurrenceStatus::Pending, $sibling->fresh()->status);
    }

    public function test_kid_cannot_submit_twice_or_after_missed()
    {
        $submitted = $this->occurrence(['status' => OccurrenceStatus::Submitted]);
        $missed = $this->occurrence(['status' => OccurrenceStatus::Missed, 'due_at' => now()->subHours(5)]);

        $this->actingAs($this->kid)->post("/occurrences/{$submitted->id}/submit")->assertStatus(409);
        $this->actingAs($this->kid)->post("/occurrences/{$missed->id}/submit")->assertStatus(409);
    }

    public function test_parent_approval_awards_points_once()
    {
        $occurrence = $this->occurrence(['status' => OccurrenceStatus::Submitted]);

        $this->actingAs($this->parent)->post("/occurrences/{$occurrence->id}/approve")->assertRedirect();
        $this->actingAs($this->parent)->post("/occurrences/{$occurrence->id}/approve")->assertStatus(409);

        $occurrence->refresh();
        $this->assertSame(OccurrenceStatus::Approved, $occurrence->status);
        $this->assertTrue($occurrence->reviewer->is($this->parent));
        $this->assertSame(5, $this->kid->fresh()->points_balance);
        $this->assertDatabaseHas('point_transactions', [
            'kid_id' => $this->kid->id,
            'delta' => 5,
            'reason' => PointReason::Chore->value,
            'source_type' => Occurrence::class,
            'source_id' => $occurrence->id,
        ]);
    }

    public function test_parent_rejection_sends_it_back_to_pending_with_a_note()
    {
        $occurrence = $this->occurrence(['status' => OccurrenceStatus::Submitted, 'reminders_sent' => 2]);

        $this->actingAs($this->parent)->post("/occurrences/{$occurrence->id}/reject", ['note' => 'Hair is still dry'])
            ->assertRedirect();

        $occurrence->refresh();
        $this->assertSame(OccurrenceStatus::Pending, $occurrence->status);
        $this->assertSame('Hair is still dry', $occurrence->review_note);
        $this->assertNull($occurrence->submitted_at);
        $this->assertSame(0, $this->kid->fresh()->points_balance);
    }

    public function test_kids_cannot_approve()
    {
        $occurrence = $this->occurrence(['status' => OccurrenceStatus::Submitted]);

        $this->actingAs($this->kid)->post("/occurrences/{$occurrence->id}/approve")
            ->assertRedirect(route('kid.today', absolute: false));
        $this->assertSame(OccurrenceStatus::Submitted, $occurrence->fresh()->status);
    }

    public function test_dashboard_lists_submitted_chores_awaiting_approval()
    {
        $this->occurrence(['status' => OccurrenceStatus::Submitted]);
        $this->occurrence(['due_at' => '2026-10-12 23:00:00']);

        $this->actingAs($this->parent)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('awaitingApproval', 1)
                ->where('awaitingApproval.0.kid.id', $this->kid->id)
                ->has('kids', 1)
                ->where('kids.0.today_total', 2)
                ->where('kids.0.today_done', 1));
    }
}
