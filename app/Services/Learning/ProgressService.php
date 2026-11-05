<?php

namespace App\Services\Learning;

use App\Enums\StageStatus;
use App\Enums\SubmissionStatus;
use App\Models\Achievement;
use App\Models\Exercise;
use App\Models\ExerciseSubmission;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Stage;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserLessonProgress;
use App\Models\UserStageProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProgressService
{
    public function __construct(private LearningTransaction $transaction, private JourneyService $journey) {}

    public function start(Stage $stage, User $user): Stage
    {
        return $this->transaction->run($stage->learning_path_id, $user, function (LearningPath $path, User $user) use ($stage) {
            $stage->refresh()->unsetRelations();
            Gate::forUser($user)->authorize('view', $stage);
            $this->ensureStarted($stage, $user);

            return $this->journey->stage($stage, $user);
        });
    }

    public function completeLesson(Lesson $lesson, User $user): array
    {
        return $this->transaction->run($lesson->stage->learning_path_id, $user, function (LearningPath $path, User $user) use ($lesson) {
            $lesson->refresh()->unsetRelations();
            Gate::forUser($user)->authorize('view', $lesson);
            $this->ensureStarted($lesson->stage, $user);
            UserLessonProgress::firstOrCreate(['user_id' => $user->id, 'lesson_id' => $lesson->id], ['completed_at' => now()]);
            $achievements = $this->evaluate($lesson->stage, $user);

            return [
                'lesson' => $this->journey->lesson($lesson, $user),
                ...$this->result($lesson->stage, $user, $achievements),
            ];
        });
    }

    public function submit(Exercise $exercise, User $user, string $optionId): array
    {
        return $this->transaction->run($exercise->stage->learning_path_id, $user, function (LearningPath $path, User $user) use ($exercise, $optionId) {
            $exercise->refresh()->unsetRelations();
            Gate::forUser($user)->authorize('view', $exercise);
            $option = $exercise->options()->find($optionId);
            if (! $option) {
                throw ValidationException::withMessages(['selected_option_id' => ['A alternativa deve pertencer ao exercício.']]);
            }
            $this->ensureStarted($exercise->stage, $user);
            $previouslyCorrect = $exercise->submissions()->where('user_id', $user->id)->where('status', SubmissionStatus::CORRECT)->exists();
            $xp = $option->is_correct && ! $previouslyCorrect ? $exercise->points : 0;
            $submission = ExerciseSubmission::create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'selected_option_id' => $option->id,
                'status' => $option->is_correct ? SubmissionStatus::CORRECT : SubmissionStatus::INCORRECT,
                'score_awarded' => $xp,
                'submitted_at' => now(),
            ]);
            if ($xp > 0) {
                $user->total_xp += $xp;
                $user->current_level = intdiv($user->total_xp, config('devquest.xp_per_level')) + 1;
                $user->save();
            }
            $achievements = $this->evaluate($exercise->stage, $user);

            return [
                'submission' => $submission,
                'explanation' => ($option->is_correct || $previouslyCorrect) ? $exercise->explanation : null,
                ...$this->result($exercise->stage, $user, $achievements),
            ];
        });
    }

    private function ensureStarted(Stage $stage, User $user): UserStageProgress
    {
        $progress = UserStageProgress::firstOrCreate(['user_id' => $user->id, 'stage_id' => $stage->id], [
            'status' => StageStatus::IN_PROGRESS,
            'started_at' => now(),
        ]);
        if (in_array($progress->status, [StageStatus::LOCKED, StageStatus::AVAILABLE], true)) {
            $progress->update(['status' => StageStatus::IN_PROGRESS, 'started_at' => $progress->started_at ?? now()]);
        }

        return $progress;
    }

    private function evaluate(Stage $stage, User $user): Collection
    {
        $progress = $this->ensureStarted($stage, $user);
        $exercises = $stage->exercises()->where('is_published', true)->withExists([
            'submissions as attempted' => fn ($q) => $q->where('user_id', $user->id),
            'submissions as solved' => fn ($q) => $q->where('user_id', $user->id)->where('status', SubmissionStatus::CORRECT),
        ])->get();
        $totalPoints = $exercises->sum('points');
        $score = $totalPoints > 0 ? intdiv(100 * $exercises->where('solved', true)->sum('points'), $totalPoints) : 0;
        $unfinishedLessons = $stage->lessons()->where('is_published', true)
            ->whereDoesntHave('userProgress', fn ($q) => $q->where('user_id', $user->id))->exists();
        $progress->best_score = max($progress->best_score, $score);
        if (! $unfinishedLessons && $exercises->isNotEmpty() && $exercises->every(fn ($exercise) => (bool) $exercise->attempted) && $score >= $stage->minimum_score) {
            $progress->status = StageStatus::COMPLETED;
            $progress->completed_at ??= now();
        }
        $progress->save();

        $slugs = [];
        if ($user->exerciseSubmissions()->where('status', SubmissionStatus::CORRECT)->exists()) {
            $slugs[] = 'first-correct-answer';
        }
        if ($progress->status === StageStatus::COMPLETED) {
            $slugs[] = 'first-completed-stage';
            $unfinishedStages = $stage->learningPath->stages()->where('is_published', true)
                ->whereDoesntHave('userProgress', fn ($q) => $q->where('user_id', $user->id)->where('status', StageStatus::COMPLETED))->exists();
            if (! $unfinishedStages) {
                $slugs[] = 'first-completed-path';
            }
        }
        $earned = collect();
        foreach (Achievement::whereIn('slug', $slugs)->get() as $achievement) {
            $award = UserAchievement::firstOrCreate(['user_id' => $user->id, 'achievement_id' => $achievement->id], ['earned_at' => now()]);
            if ($award->wasRecentlyCreated) {
                $achievement->setAttribute('earned_at', $award->earned_at);
                $earned->push($achievement);
            }
        }

        return $earned;
    }

    private function result(Stage $stage, User $user, Collection $achievements): array
    {
        $stage = $this->journey->stage($stage, $user);

        return [
            'stage' => $stage,
            'user' => $user,
            'new_achievements' => $achievements,
            'next_stage_id' => $stage->access_status === StageStatus::COMPLETED ? $stage->next_stage_id : null,
        ];
    }
}
