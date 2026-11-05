<?php

namespace App\Services\Learning;

use App\Models\LearningPath;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

class LearningTransaction
{
    public function run(string $pathId, ?User $user, Closure $callback): mixed
    {
        // All writers lock the path before the user. SQLite uses BEGIN IMMEDIATE;
        // databases with row locks serialize the same path/user via FOR UPDATE.
        return DB::transaction(function () use ($pathId, $user, $callback) {
            $path = LearningPath::query()->lockForUpdate()->findOrFail($pathId);
            $lockedUser = $user ? User::query()->lockForUpdate()->findOrFail($user->id) : null;

            return $callback($path, $lockedUser);
        }, 5);
    }
}
