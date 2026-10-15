<?php

namespace Tests\Feature\Parent;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FamilyTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_sees_family_without_secrets()
    {
        $parent = User::factory()->create();
        User::factory()->kid()->create();

        $this->actingAs($parent)->get('/family')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('parent/Family')
                ->has('members', 2)
                ->missing('members.0.pin')
                ->missing('members.0.password'));
    }

    public function test_parent_can_add_a_kid_with_pin()
    {
        $parent = User::factory()->create();

        $this->actingAs($parent)->post('/family', [
            'role' => 'kid',
            'name' => 'Sam',
            'pin' => '4321',
            'avatar_emoji' => '🦖',
            'color' => 'emerald',
        ])->assertRedirect();

        $kid = User::where('name', 'Sam')->first();
        $this->assertSame(Role::Kid, $kid->role);
        $this->assertNull($kid->email);
        $this->assertTrue(Hash::check('4321', $kid->pin));
    }

    public function test_parent_can_add_another_parent()
    {
        $parent = User::factory()->create();

        $this->actingAs($parent)->post('/family', [
            'role' => 'parent',
            'name' => 'Dad',
            'email' => 'dad@example.com',
            'password' => 'secret-password',
        ])->assertRedirect();

        $dad = User::where('email', 'dad@example.com')->first();
        $this->assertTrue($dad->isParent());
        $this->assertTrue(Hash::check('secret-password', $dad->password));
    }

    public function test_kid_requires_four_digit_pin_and_parent_requires_email()
    {
        $parent = User::factory()->create();

        $this->actingAs($parent)->post('/family', ['role' => 'kid', 'name' => 'Sam', 'pin' => '12'])
            ->assertSessionHasErrors('pin');
        $this->actingAs($parent)->post('/family', ['role' => 'parent', 'name' => 'Dad', 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');
    }

    public function test_updating_a_kid_keeps_pin_when_blank()
    {
        $parent = User::factory()->create();
        $kid = User::factory()->kid()->create();

        $this->actingAs($parent)->put("/family/{$kid->id}", [
            'name' => 'Sammy',
            'pin' => '',
            'avatar_emoji' => '🚀',
            'color' => 'sky',
        ])->assertRedirect();

        $kid->refresh();
        $this->assertSame('Sammy', $kid->name);
        $this->assertTrue(Hash::check('1234', $kid->pin));
    }

    public function test_parent_can_remove_a_kid_but_not_themselves()
    {
        $parent = User::factory()->create();
        $kid = User::factory()->kid()->create();

        $this->actingAs($parent)->delete("/family/{$kid->id}")->assertRedirect();
        $this->assertModelMissing($kid);

        $this->actingAs($parent)->delete("/family/{$parent->id}")->assertForbidden();
        $this->assertModelExists($parent);
    }

    public function test_kids_cannot_manage_family()
    {
        $kid = User::factory()->kid()->create();

        $this->actingAs($kid)->get('/family')->assertRedirect(route('kid.today', absolute: false));
        $this->actingAs($kid)->post('/family', ['role' => 'kid', 'name' => 'X', 'pin' => '1111'])
            ->assertRedirect(route('kid.today', absolute: false));
        $this->assertSame(1, User::count());
    }
}
