<?php

namespace App\Policies;

use App\Enums\StageStatus;
use App\Models\Stage;
use App\Models\User;

class StagePolicy
{
    public function view(User $user, Stage $stage): bool
    {
        if (! $stage->is_published || ! $stage->learningPath->is_published) {
            return false;
        }

        $previous = $stage->learningPath->stages()->where('is_published', true)
            ->where('position', '<', $stage->position)->reorder('position', 'desc')->first();

        return ! $previous || $previous->userProgress()->where('user_id', $user->id)
            ->where('status', StageStatus::COMPLETED)->exists();
    }
}
