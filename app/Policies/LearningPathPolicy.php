<?php

namespace App\Policies;

use App\Models\LearningPath;
use App\Models\User;

class LearningPathPolicy
{
    public function view(User $user, LearningPath $path): bool
    {
        return $path->is_published;
    }
}
