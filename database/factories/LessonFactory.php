<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lesson> */
class LessonFactory extends Factory
{
    public function definition(): array
    {
        return ['stage_id' => Stage::factory(), 'title' => fake()->sentence(3), 'content' => '# Aula', 'position' => 1];
    }

    public function published(): static
    {
        return $this->state(['is_published' => true]);
    }
}
