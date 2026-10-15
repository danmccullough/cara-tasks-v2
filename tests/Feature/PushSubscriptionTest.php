<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private array $subscription = [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        'keys' => ['p256dh' => 'BPubKey', 'auth' => 'AuthToken'],
        'contentEncoding' => 'aes128gcm',
    ];

    public function test_kid_can_subscribe_their_device()
    {
        $kid = User::factory()->kid()->create();

        $this->actingAs($kid)->postJson('/push-subscriptions', $this->subscription)->assertNoContent();

        $this->assertSame(1, $kid->pushSubscriptions()->count());
        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $kid->id,
            'endpoint' => $this->subscription['endpoint'],
            'public_key' => 'BPubKey',
            'auth_token' => 'AuthToken',
        ]);
    }

    public function test_resubscribing_the_same_device_does_not_duplicate()
    {
        $parent = User::factory()->create();

        $this->actingAs($parent)->postJson('/push-subscriptions', $this->subscription);
        $this->actingAs($parent)->postJson('/push-subscriptions', $this->subscription);

        $this->assertSame(1, $parent->pushSubscriptions()->count());
    }

    public function test_device_can_unsubscribe()
    {
        $parent = User::factory()->create();
        $this->actingAs($parent)->postJson('/push-subscriptions', $this->subscription);

        $this->actingAs($parent)->deleteJson('/push-subscriptions', ['endpoint' => $this->subscription['endpoint']])->assertNoContent();

        $this->assertSame(0, $parent->pushSubscriptions()->count());
    }

    public function test_validation_and_auth()
    {
        $this->postJson('/push-subscriptions', $this->subscription)->assertUnauthorized();

        $this->actingAs(User::factory()->create())->postJson('/push-subscriptions', ['endpoint' => 'not-a-url'])
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }

    public function test_vapid_public_key_is_shared_with_the_frontend()
    {
        config(['webpush.vapid.public_key' => 'PUBLIC-KEY']);

        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('vapidPublicKey', 'PUBLIC-KEY'));
    }
}
