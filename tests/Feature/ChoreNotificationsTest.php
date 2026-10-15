<?php

namespace Tests\Feature;

use App\Enums\OccurrenceStatus;
use App\Models\Occurrence;
use App\Models\User;
use App\Notifications\ChoreApproved;
use App\Notifications\ChoreReminder;
use App\Notifications\ChoreSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class ChoreNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_submitting_notifies_parents()
    {
        $parent = User::factory()->create();
        $occurrence = Occurrence::factory()->create();

        $this->actingAs($occurrence->kid)->post("/occurrences/{$occurrence->id}/submit");

        Notification::assertSentTo($parent, ChoreSubmitted::class);
    }

    public function test_approving_notifies_the_kid()
    {
        $parent = User::factory()->create();
        $occurrence = Occurrence::factory()->submitted()->create();

        $this->actingAs($parent)->post("/occurrences/{$occurrence->id}/approve");

        Notification::assertSentTo($occurrence->kid, ChoreApproved::class);
    }

    public function test_channels_follow_the_users_preferences()
    {
        $occurrence = Occurrence::factory()->create();
        $kid = $occurrence->kid;

        $this->assertSame(['database', WebPushChannel::class], (new ChoreReminder($occurrence))->via($kid));

        $kid->notify_channels = ['database'];
        $this->assertSame(['database'], (new ChoreReminder($occurrence))->via($kid));
    }

    public function test_reminder_push_payload_is_friendly_and_links_to_today()
    {
        $occurrence = Occurrence::factory()->create(['status' => OccurrenceStatus::Pending]);
        $occurrence->chore->update(['title' => 'Shower', 'icon' => '🚿']);

        $message = (new ChoreReminder($occurrence->fresh()))->toWebPush($occurrence->kid, null)->toArray();

        $this->assertSame('🚿 Time to: Shower', $message['title']);
        $this->assertSame('/today', $message['data']['url']);
        $this->assertSame("occurrence-{$occurrence->id}", $message['tag']);
        $this->assertTrue($message['renotify']);
    }
}
