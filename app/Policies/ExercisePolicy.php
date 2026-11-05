<?php

namespace App\Policies;

use App\Enums\ExerciseType;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ExercisePolicy
{
    public function view(User $user, Exercise $exercise): bool
    {
        return $exercise->is_published && $exercise->type === ExerciseType::MULTIPLE_CHOICE
            && Gate::forUser($user)->allows('view', $exercise->stage);
    }
}
