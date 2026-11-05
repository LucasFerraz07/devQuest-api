<?php

namespace Database\Factories;

use App\Models\LearningPath;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LearningPath> */
class LearningPathFactory extends Factory
{
    public function definition(): array
    {
        return ['title' => fake()->sentence(3), 'slug' => fake()->unique()->slug(3), 'created_by' => User::factory()->admin()];
    }

    public function published(): static
    {
        return $this->state(['is_published' => true]);
    }
}
