<?php

namespace Database\Factories;

use App\Enums\OccurrenceStatus;
use App\Models\Assignment;
use App\Models\Occurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Occurrence>
 */
class OccurrenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'chore_id' => fn (array $attrs) => Assignment::find($attrs['assignment_id'])->chore_id,
            'kid_id' => fn (array $attrs) => Assignment::find($attrs['assignment_id'])->kid_id,
            'due_at' => now()->addHour(),
            'status' => OccurrenceStatus::Pending,
        ];
    }

    public function forAssignment(Assignment $assignment): static
    {
        return $this->state(fn () => [
            'assignment_id' => $assignment->id,
            'chore_id' => $assignment->chore_id,
            'kid_id' => $assignment->kid_id,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => OccurrenceStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function status(OccurrenceStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
