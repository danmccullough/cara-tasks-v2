<?php

namespace Tests\Feature;

use App\Enums\PointReason;
use App\Enums\RedemptionStatus;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\User;
use App\Services\PointsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardsTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    private User $kid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parent = User::factory()->create();
        $this->kid = User::factory()->kid()->create();
    }

    private function givePoints(int $points): void
    {
        app(PointsService::class)->record($this->kid, $points, PointReason::Adjustment);
    }

    public function test_parent_can_manage_rewards()
    {
        $this->actingAs($this->parent)->post('/rewards', ['title' => 'Pick dinner', 'icon' => '🍕', 'cost' => 30])->assertRedirect();
        $reward = Reward::firstWhere('title', 'Pick dinner');

        $this->actingAs($this->parent)->put("/rewards/{$reward->id}", ['title' => 'Pick dinner', 'icon' => '🍕', 'cost' => 25, 'active' => true]);
        $this->assertSame(25, $reward->fresh()->cost);

        $this->actingAs($this->parent)->get('/rewards')->assertInertia(fn ($page) => $page->component('parent/Rewards')->has('rewards', 1));

        $this->actingAs($this->parent)->delete("/rewards/{$reward->id}");
        $this->assertModelMissing($reward);
    }

    public function test_kid_sees_active_rewards_and_balance()
    {
        Reward::factory()->create(['title' => 'Ice cream']);
        Reward::factory()->create(['active' => false]);
        $this->givePoints(12);

        $this->actingAs($this->kid)->get('/reward-shop')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('kid/Rewards')
                ->has('rewards', 1)
                ->where('rewards.0.title', 'Ice cream')
                ->where('points', 12));
    }

    public function test_kid_can_redeem_when_they_have_enough_points()
    {
        $reward = Reward::factory()->create(['cost' => 20]);
        $this->givePoints(25);

        $this->actingAs($this->kid)->post("/rewards/{$reward->id}/redeem")->assertRedirect();

        $this->assertSame(5, $this->kid->fresh()->points_balance);
        $redemption = RewardRedemption::first();
        $this->assertSame(RedemptionStatus::Requested, $redemption->status);
        $this->assertSame(20, $redemption->cost);
        $this->assertDatabaseHas('point_transactions', ['kid_id' => $this->kid->id, 'delta' => -20, 'reason' => 'reward']);
    }

    public function test_kid_cannot_redeem_without_enough_points_or_an_inactive_reward()
    {
        $expensive = Reward::factory()->create(['cost' => 100]);
        $inactive = Reward::factory()->create(['cost' => 1, 'active' => false]);
        $this->givePoints(10);

        $this->actingAs($this->kid)->post("/rewards/{$expensive->id}/redeem")->assertSessionHasErrors('points');
        $this->actingAs($this->kid)->post("/rewards/{$inactive->id}/redeem")->assertNotFound();

        $this->assertSame(10, $this->kid->fresh()->points_balance);
        $this->assertSame(0, RewardRedemption::count());
    }

    public function test_parent_fulfills_a_redemption()
    {
        $reward = Reward::factory()->create(['cost' => 20]);
        $this->givePoints(20);
        $this->actingAs($this->kid)->post("/rewards/{$reward->id}/redeem");
        $redemption = RewardRedemption::first();

        $this->actingAs($this->parent)->post("/redemptions/{$redemption->id}/fulfill")->assertRedirect();

        $redemption->refresh();
        $this->assertSame(RedemptionStatus::Fulfilled, $redemption->status);
        $this->assertSame($this->parent->id, $redemption->handled_by);
        $this->assertSame(0, $this->kid->fresh()->points_balance);
    }

    public function test_parent_denying_refunds_points_once()
    {
        $reward = Reward::factory()->create(['cost' => 20]);
        $this->givePoints(20);
        $this->actingAs($this->kid)->post("/rewards/{$reward->id}/redeem");
        $redemption = RewardRedemption::first();

        $this->actingAs($this->parent)->post("/redemptions/{$redemption->id}/deny")->assertRedirect();
        $this->actingAs($this->parent)->post("/redemptions/{$redemption->id}/deny")->assertStatus(409);

        $this->assertSame(RedemptionStatus::Denied, $redemption->fresh()->status);
        $this->assertSame(20, $this->kid->fresh()->points_balance);
    }

    public function test_dashboard_lists_requested_redemptions()
    {
        RewardRedemption::create(['reward_id' => Reward::factory()->create()->id, 'kid_id' => $this->kid->id, 'cost' => 20, 'status' => RedemptionStatus::Requested]);

        $this->actingAs($this->parent)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('redemptions', 1)->where('redemptions.0.kid.id', $this->kid->id));
    }

    public function test_kids_cannot_manage_rewards_or_handle_redemptions()
    {
        $reward = Reward::factory()->create(['cost' => 5]);
        $this->givePoints(5);
        $this->actingAs($this->kid)->post("/rewards/{$reward->id}/redeem");
        $redemption = RewardRedemption::first();

        $this->actingAs($this->kid)->post('/rewards', ['title' => 'Free money', 'icon' => '💰', 'cost' => 0])
            ->assertRedirect(route('kid.today', absolute: false));
        $this->actingAs($this->kid)->post("/redemptions/{$redemption->id}/deny")
            ->assertRedirect(route('kid.today', absolute: false));

        $this->assertSame(1, Reward::count());
        $this->assertSame(RedemptionStatus::Requested, $redemption->fresh()->status);
    }
}
