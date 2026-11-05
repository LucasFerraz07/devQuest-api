<?php

namespace App\Policies;

use App\Enums\UserType;
use App\Models\User;

class ContentPolicy
{
    public function manage(User $user): bool
    {
        return $user->role === UserType::ADMIN;
    }
}
