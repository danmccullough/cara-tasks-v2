<?php

namespace Database\Factories;

use App\Models\Chore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Assignment>
 */
class AssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chore_id' => Chore::factory(),
            'kid_id' => User::factory()->kid(),
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
            'due_time' => '19:00',
            'remind_before_min' => 15,
            'nag_every_min' => 10,
            'max_nags' => 3,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'active' => true,
        ];
    }
}
