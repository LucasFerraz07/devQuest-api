<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Exercise> */
class ExerciseFactory extends Factory
{
    public function definition(): array
    {
        return ['stage_id' => Stage::factory(), 'title' => fake()->sentence(3), 'statement' => 'Qual alternativa está correta?', 'type' => 'multiple_choice', 'position' => 1, 'points' => 10];
    }

    public function published(): static
    {
        return $this->state(['is_published' => true]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Exercise $exercise) {
            $exercise->options()->createMany([
                ['content' => 'Correta', 'is_correct' => true, 'position' => 1],
                ['content' => 'Incorreta', 'is_correct' => false, 'position' => 2],
            ]);
        });
    }
}
