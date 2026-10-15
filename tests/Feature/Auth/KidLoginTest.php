<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KidLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_kid_picker_lists_only_kids()
    {
        $kid = User::factory()->kid()->create(['name' => 'Sam']);
        User::factory()->create(['name' => 'Mom']);

        $this->get('/kid-login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/KidLogin')
                ->has('kids', 1)
                ->where('kids.0.id', $kid->id)
                ->missing('kids.0.pin'));
    }

    public function test_kid_can_login_with_correct_pin()
    {
        $kid = User::factory()->kid()->create();

        $this->post('/kid-login', ['kid_id' => $kid->id, 'pin' => '1234'])
            ->assertRedirect(route('kid.today', absolute: false));

        $this->assertAuthenticatedAs($kid);
    }

    public function test_kid_cannot_login_with_wrong_pin()
    {
        $kid = User::factory()->kid()->create();

        $this->post('/kid-login', ['kid_id' => $kid->id, 'pin' => '0000'])
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_parent_cannot_login_through_kid_pin_form()
    {
        $parent = User::factory()->create(['pin' => '1234']);

        $this->post('/kid-login', ['kid_id' => $parent->id, 'pin' => '1234'])
            ->assertSessionHasErrors('kid_id');

        $this->assertGuest();
    }

    public function test_kid_is_redirected_away_from_parent_pages()
    {
        $kid = User::factory()->kid()->create();

        $this->actingAs($kid)->get('/dashboard')->assertRedirect(route('kid.today', absolute: false));
    }

    public function test_parent_is_redirected_away_from_kid_pages()
    {
        $parent = User::factory()->create();

        $this->actingAs($parent)->get('/today')->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_kid_logout_returns_to_the_kid_picker()
    {
        $kid = User::factory()->kid()->create();

        $this->actingAs($kid)->post('/logout')->assertRedirect(route('kid.login'));
        $this->assertGuest();
    }

    public function test_registration_is_closed_once_a_user_exists()
    {
        User::factory()->create();

        $this->get('/register')->assertForbidden();
        $this->post('/register', [
            'name' => 'Stranger',
            'email' => 'stranger@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertForbidden();
    }
}
