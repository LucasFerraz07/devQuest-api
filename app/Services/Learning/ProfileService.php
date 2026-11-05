<?php

namespace App\Services\Learning;

use App\Enums\StageStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProfileService
{
    public function __construct(private JourneyService $journey) {}

    public function submissions(User $user, array $filters): LengthAwarePaginator
    {
        return $user->exerciseSubmissions()
            ->when($filters['exercise_id'] ?? null, fn ($q, $id) => $q->where('exercise_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('submitted_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 20);
    }

    public function achievements(User $user, array $filters): LengthAwarePaginator
    {
        return $user->achievements()->orderByPivot('earned_at', 'desc')->orderBy('achievements.id')->paginate($filters['per_page'] ?? 20);
    }

    public function dashboard(User $user): array
    {
        $paths = $this->journey->paths($user)->where('is_published', true)
            ->whereHas('stageProgress', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('title')->orderBy('id')->get();
        $nextActivity = null;
        foreach ($paths as $path) {
            if ($path->completed_stages_count >= $path->stages_count) {
                continue;
            }
            $details = $this->journey->path($path, $user);
            $stage = $details->stages->first(fn ($stage) => in_array($stage->access_status, [StageStatus::AVAILABLE, StageStatus::IN_PROGRESS], true));
            if ($stage) {
                $nextLesson = $stage->lessons()->where('is_published', true)
                    ->whereDoesntHave('userProgress', fn ($q) => $q->where('user_id', $user->id))->first();
                $nextExercise = $nextLesson ? null : $stage->exercises()->where('is_published', true)
                    ->whereDoesntHave('submissions', fn ($q) => $q->where('user_id', $user->id)->where('status', 'correct'))->first();
                $nextActivity = ['learning_path_id' => $path->id, 'stage_id' => $stage->id,
                    'lesson_id' => $nextLesson?->id, 'exercise_id' => $nextExercise?->id];
                break;
            }
        }

        return [
            'user' => $user,
            'started_paths_count' => $paths->count(),
            'completed_stages_count' => $user->stageProgress()->where('status', StageStatus::COMPLETED)->count(),
            'achievements_count' => $user->userAchievements()->count(),
            'learning_paths' => $paths,
            'next_activity' => $nextActivity,
        ];
    }
}
