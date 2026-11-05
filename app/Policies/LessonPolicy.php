<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class LessonPolicy
{
    public function view(User $user, Lesson $lesson): bool
    {
        return $lesson->is_published && Gate::forUser($user)->allows('view', $lesson->stage);
    }
}
