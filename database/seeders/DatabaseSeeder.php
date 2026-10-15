<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Chore;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Demo data for local development. Parents log in with password "password"; kid PINs are 1234 and 5678.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create(['name' => 'Mom', 'email' => 'mom@example.com']);
        User::factory()->create(['name' => 'Dad', 'email' => 'dad@example.com']);

        $sam = User::factory()->kid()->create(['name' => 'Sam', 'pin' => '1234', 'avatar_emoji' => '🦖', 'color' => 'emerald']);
        $alex = User::factory()->kid()->create(['name' => 'Alex', 'pin' => '5678', 'avatar_emoji' => '🚀', 'color' => 'violet']);

        $chores = [
            'shower' => Chore::create(['title' => 'Shower', 'icon' => '🚿', 'points' => 10, 'instructions' => "Wash hair\nWash body\nDry off and hang up towel"]),
            'teethAm' => Chore::create(['title' => 'Brush teeth (morning)', 'icon' => '🪥', 'points' => 5]),
            'teethPm' => Chore::create(['title' => 'Brush teeth (night)', 'icon' => '🪥', 'points' => 5]),
            'meds' => Chore::create(['title' => 'Take meds', 'icon' => '💊', 'points' => 5]),
        ];

        foreach ([$sam, $alex] as $kid) {
            Assignment::create(['chore_id' => $chores['teethAm']->id, 'kid_id' => $kid->id, 'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'due_time' => '07:30', 'start_date' => today()]);
            Assignment::create(['chore_id' => $chores['teethPm']->id, 'kid_id' => $kid->id, 'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'due_time' => '20:30', 'start_date' => today()]);
        }
        Assignment::create(['chore_id' => $chores['shower']->id, 'kid_id' => $sam->id, 'days_of_week' => [1, 3, 5, 7], 'due_time' => '19:00', 'start_date' => today()]);
        Assignment::create(['chore_id' => $chores['meds']->id, 'kid_id' => $sam->id, 'days_of_week' => [1, 2, 3, 4, 5, 6, 7], 'due_time' => '08:00', 'start_date' => today()]);

        Reward::create(['title' => '30 min screen time', 'icon' => '🎮', 'cost' => 20]);
        Reward::create(['title' => 'Pick dinner', 'icon' => '🍕', 'cost' => 50]);
        Reward::create(['title' => 'Ice cream trip', 'icon' => '🍦', 'cost' => 100]);

        Artisan::call('occurrences:generate');
    }
}
