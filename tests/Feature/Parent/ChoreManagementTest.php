<?php

namespace Tests\Feature\Parent;

use App\Models\Assignment;
use App\Models\Chore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChoreManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();

        config(['chores.timezone' => 'America/New_York', 'chores.generate_days_ahead' => 7]);
        $this->travelTo('2026-10-12 12:00:00'); // Monday, 08:00 New York
        $this->parent = User::factory()->create();
    }

    public function test_chores_page_lists_chores_with_assignments_and_kids()
    {
        $assignment = Assignment::factory()->create();

        $this->actingAs($this->parent)->get('/chores')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('parent/Chores')
                ->has('chores', 1)
                ->has('chores.0.assignments', 1)
                ->where('chores.0.assignments.0.kid_id', $assignment->kid_id)
                ->has('kids', 1));
    }

    public function test_parent_can_create_update_and_delete_a_chore()
    {
        $this->actingAs($this->parent)->post('/chores', [
            'title' => 'Shower',
            'icon' => '🚿',
            'points' => 10,
            'instructions' => 'Wash hair too',
        ])->assertRedirect();

        $chore = Chore::firstWhere('title', 'Shower');
        $this->assertSame(10, $chore->points);

        $this->actingAs($this->parent)->put("/chores/{$chore->id}", [
            'title' => 'Take a shower',
            'icon' => '🚿',
            'points' => 8,
            'active' => true,
        ])->assertRedirect();
        $this->assertSame('Take a shower', $chore->fresh()->title);

        $this->actingAs($this->parent)->delete("/chores/{$chore->id}")->assertRedirect();
        $this->assertModelMissing($chore);
    }

    public function test_chore_validation()
    {
        $this->actingAs($this->parent)->post('/chores', ['title' => '', 'points' => -1])
            ->assertSessionHasErrors(['title', 'points']);
    }

    public function test_creating_an_assignment_generates_occurrences()
    {
        $chore = Chore::factory()->create();
        $kid = User::factory()->kid()->create();

        $this->actingAs($this->parent)->post('/assignments', [
            'chore_id' => $chore->id,
            'kid_id' => $kid->id,
            'days_of_week' => [1, 2, 3, 4, 5],
            'due_time' => '19:00',
            'remind_before_min' => 15,
            'nag_every_min' => 10,
            'max_nags' => 3,
            'start_date' => '2026-10-12',
        ])->assertRedirect();

        $assignment = Assignment::first();
        $this->assertSame([1, 2, 3, 4, 5], $assignment->days_of_week);
        // Mon..Fri this week + Mon the 19th.
        $this->assertSame(6, $assignment->occurrences()->count());
    }

    public function test_updating_an_assignment_resyncs_future_occurrences()
    {
        $assignment = Assignment::factory()->create(['days_of_week' => [1, 2, 3, 4, 5, 6, 7]]);
        $this->actingAs($this->parent)->put("/assignments/{$assignment->id}", [
            'days_of_week' => [6, 7],
            'due_time' => '09:00',
            'remind_before_min' => 5,
            'nag_every_min' => 5,
            'max_nags' => 2,
            'start_date' => '2026-10-12',
            'active' => true,
        ])->assertRedirect();

        // Sat 17th and Sun 18th at 09:00 EDT.
        $this->assertSame(
            ['2026-10-17 13:00', '2026-10-18 13:00'],
            $assignment->occurrences()->orderBy('due_at')->get()->map(fn ($o) => $o->due_at->format('Y-m-d H:i'))->all(),
        );
    }

    public function test_assignment_validation()
    {
        $chore = Chore::factory()->create();

        $this->actingAs($this->parent)->post('/assignments', [
            'chore_id' => $chore->id,
            'kid_id' => $this->parent->id, // not a kid
            'days_of_week' => [],
            'due_time' => '25:00',
            'start_date' => '2026-10-12',
        ])->assertSessionHasErrors(['kid_id', 'days_of_week', 'due_time']);
    }

    public function test_parent_can_delete_an_assignment()
    {
        $assignment = Assignment::factory()->create();

        $this->actingAs($this->parent)->delete("/assignments/{$assignment->id}")->assertRedirect();

        $this->assertModelMissing($assignment);
    }

    public function test_kids_cannot_manage_chores()
    {
        $kid = User::factory()->kid()->create();

        $this->actingAs($kid)->post('/chores', ['title' => 'Nope', 'icon' => '❌', 'points' => 1000])
            ->assertRedirect(route('kid.today', absolute: false));
        $this->assertSame(0, Chore::count());
    }
}
