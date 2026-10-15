<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chore>
 */
class ChoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(['Shower', 'Brush teeth', 'Take meds', 'Feed the dog', 'Pack backpack']),
            'icon' => fake()->randomElement(['🚿', '🪥', '💊', '🐶', '🎒']),
            'instructions' => null,
            'points' => 5,
            'active' => true,
        ];
    }
}
