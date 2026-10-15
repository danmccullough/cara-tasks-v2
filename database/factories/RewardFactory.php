<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reward>
 */
class RewardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(['30 min screen time', 'Pick dinner', 'Ice cream trip']),
            'icon' => fake()->randomElement(['🎮', '🍕', '🍦']),
            'cost' => 20,
            'active' => true,
        ];
    }
}
