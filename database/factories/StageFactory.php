<?php

namespace Database\Factories;

use App\Models\LearningPath;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Stage> */
class StageFactory extends Factory
{
    public function definition(): array
    {
        return ['learning_path_id' => LearningPath::factory(), 'title' => fake()->sentence(3), 'position' => 1, 'minimum_score' => 100];
    }

    public function published(): static
    {
        return $this->state(['is_published' => true]);
    }
}
