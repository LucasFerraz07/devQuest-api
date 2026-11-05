<?php

namespace App\Services\Learning;

use App\Enums\StageStatus;
use App\Enums\SubmissionStatus;
use App\Models\Exercise;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class JourneyService
{
    public function catalogue(User $user, array $filters): LengthAwarePaginator
    {
        return $this->paths($user)->where('is_published', true)
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', '%'.$search.'%'))
            ->orderBy('title')->orderBy('id')->paginate($filters['per_page'] ?? 12);
    }

    public function paths(User $user): Builder
    {
        return LearningPath::query()->withCount([
            'stages' => fn ($q) => $q->where('is_published', true),
            'stageProgress as completed_stages_count' => fn ($q) => $q->where('user_id', $user->id)->where('status', StageStatus::COMPLETED),
            'stageProgress as started_stages_count' => fn ($q) => $q->where('user_id', $user->id),
        ]);
    }

    public function path(LearningPath $path, User $user): LearningPath
    {
        Gate::forUser($user)->authorize('view', $path);
        $path = $this->paths($user)->findOrFail($path->id);
        $path->load(['stages' => fn ($q) => $q->where('is_published', true)
            ->with(['userProgress' => fn ($q) => $q->where('user_id', $user->id)])
            ->withCount($this->stageCounts($user))]);
        $previousCompleted = true;
        foreach ($path->stages as $stage) {
            $progress = $stage->userProgress->first();
            $stage->setAttribute('access_status', $progress?->status ?? ($previousCompleted ? StageStatus::AVAILABLE : StageStatus::LOCKED));
            $previousCompleted = $progress?->status === StageStatus::COMPLETED;
        }

        return $path;
    }

    public function stage(Stage $stage, User $user): Stage
    {
        Gate::forUser($user)->authorize('view', $stage);
        $path = $this->path($stage->learningPath, $user);
        $stage = $path->stages->firstWhere('id', $stage->id);
        $stage->load([
            'lessons' => fn ($q) => $q->select(['id', 'stage_id', 'title', 'position', 'estimated_minutes'])->where('is_published', true)->with(['userProgress' => fn ($q) => $q->where('user_id', $user->id)]),
            'exercises' => fn ($q) => $q->select(['id', 'stage_id', 'title', 'type', 'difficulty', 'points', 'position'])->where('is_published', true)->withExists([
                'submissions as is_attempted' => fn ($q) => $q->where('user_id', $user->id),
                'submissions as is_solved' => fn ($q) => $q->where('user_id', $user->id)->where('status', SubmissionStatus::CORRECT),
            ]),
        ]);
        $stage->setAttribute('next_stage_id', $path->stages->first(fn ($next) => $next->position > $stage->position)?->id);

        return $stage;
    }

    public function lesson(Lesson $lesson, User $user): Lesson
    {
        Gate::forUser($user)->authorize('view', $lesson);
        $lesson->load(['userProgress' => fn ($q) => $q->where('user_id', $user->id)]);
        $lessons = $lesson->stage->lessons()->where('is_published', true)->get(['id', 'position']);
        $lesson->setAttribute('previous_lesson_id', $lessons->last(fn ($item) => $item->position < $lesson->position)?->id);
        $lesson->setAttribute('next_lesson_id', $lessons->first(fn ($item) => $item->position > $lesson->position)?->id);

        return $lesson;
    }

    public function exercise(Exercise $exercise, User $user): Exercise
    {
        Gate::forUser($user)->authorize('view', $exercise);
        $exercise->load('options')->loadExists([
            'submissions as is_attempted' => fn ($q) => $q->where('user_id', $user->id),
            'submissions as is_solved' => fn ($q) => $q->where('user_id', $user->id)->where('status', SubmissionStatus::CORRECT),
        ]);

        return $exercise;
    }

    private function stageCounts(User $user): array
    {
        return [
            'lessons' => fn ($q) => $q->where('is_published', true),
            'exercises' => fn ($q) => $q->where('is_published', true),
            'lessons as completed_lessons_count' => fn ($q) => $q->where('is_published', true)->whereHas('userProgress', fn ($q) => $q->where('user_id', $user->id)),
            'exercises as attempted_exercises_count' => fn ($q) => $q->where('is_published', true)->whereHas('submissions', fn ($q) => $q->where('user_id', $user->id)),
            'exercises as solved_exercises_count' => fn ($q) => $q->where('is_published', true)->whereHas('submissions', fn ($q) => $q->where('user_id', $user->id)->where('status', SubmissionStatus::CORRECT)),
        ];
    }
}
