<?php

namespace Tests\Support;

use App\Models\Exercise;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait LearningFixtures
{
    protected function tokenFor(User $user): string
    {
        Auth::forgetGuards();
        $token = auth('api')->login($user);
        Auth::forgetGuards();
        $this->withToken($token);

        return $token;
    }

    protected function course(int $stages = 2, int $exercises = 1, int $minimum = 100): LearningPath
    {
        $path = LearningPath::factory()->published()->create();
        for ($i = 1; $i <= $stages; $i++) {
            $stage = Stage::factory()->published()->create(['learning_path_id' => $path->id, 'position' => $i, 'minimum_score' => $minimum]);
            Lesson::factory()->published()->create(['stage_id' => $stage->id]);
            for ($j = 1; $j <= $exercises; $j++) {
                Exercise::factory()->published()->create(['stage_id' => $stage->id, 'position' => $j, 'points' => $j === 1 ? 75 : 25, 'explanation' => 'Explicação do gabarito.']);
            }
        }

        return $path->load('stages.lessons', 'stages.exercises.options');
    }
}
