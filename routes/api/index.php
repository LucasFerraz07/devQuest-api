<?php

use App\Enums\ContentType;
use App\Http\Controllers;
use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(base_path('routes/api/auth.php'));

Route::middleware('auth.api')->group(function () {
    Route::get('learning-paths', [Controllers\LearningPath\LearningPathController::class, 'index']);
    Route::get('learning-paths/{learningPath}', [Controllers\LearningPath\LearningPathController::class, 'show'])->whereUuid('learningPath');
    Route::get('stages/{stage}', [Controllers\Stage\StageController::class, 'show'])->whereUuid('stage');
    Route::post('stages/{stage}/start', [Controllers\StageProgress\StageProgressController::class, 'store'])->whereUuid('stage');
    Route::get('lessons/{lesson}', [Controllers\Lesson\LessonController::class, 'show'])->whereUuid('lesson');
    Route::put('lessons/{lesson}/completion', [Controllers\LessonCompletion\LessonCompletionController::class, 'update'])->whereUuid('lesson');
    Route::get('exercises/{exercise}', [Controllers\Exercise\ExerciseController::class, 'show'])->whereUuid('exercise');
    Route::post('exercises/{exercise}/submissions', [Controllers\Submission\SubmissionController::class, 'store'])->whereUuid('exercise');
    Route::get('me/dashboard', [Controllers\Dashboard\DashboardController::class, 'show']);
    Route::get('me/submissions', [Controllers\Submission\SubmissionController::class, 'index']);
    Route::get('me/achievements', [Controllers\Achievement\AchievementController::class, 'index']);

    Route::prefix('admin')->middleware('can:manage-content')->group(function () {
        Route::put('learning-paths/{record}/stages/order', [Admin\StageOrder\StageOrderController::class, 'update'])->whereUuid('record')->defaults('child_type', 'stages')->name('admin.learning-paths.stages.reorder');
        Route::put('stages/{record}/lessons/order', [Admin\LessonOrder\LessonOrderController::class, 'update'])->whereUuid('record')->defaults('child_type', 'lessons')->name('admin.stages.lessons.reorder');
        Route::put('stages/{record}/exercises/order', [Admin\ExerciseOrder\ExerciseOrderController::class, 'update'])->whereUuid('record')->defaults('child_type', 'exercises')->name('admin.stages.exercises.reorder');
        foreach ([
            ContentType::PATH->value => Admin\LearningPath\LearningPathController::class,
            ContentType::STAGE->value => Admin\Stage\StageController::class,
            ContentType::LESSON->value => Admin\Lesson\LessonController::class,
            ContentType::EXERCISE->value => Admin\Exercise\ExerciseController::class,
        ] as $resource => $controller) {
            Route::get($resource, [$controller, 'index'])->defaults('content_type', $resource)->name('admin.'.$resource.'.index');
            Route::post($resource, [$controller, 'store'])->defaults('content_type', $resource)->name('admin.'.$resource.'.store');
            Route::get($resource.'/{record}', [$controller, 'show'])->whereUuid('record')->defaults('content_type', $resource)->name('admin.'.$resource.'.show');
            Route::patch($resource.'/{record}', [$controller, 'update'])->whereUuid('record')->defaults('content_type', $resource)->name('admin.'.$resource.'.update');
            Route::delete($resource.'/{record}', [$controller, 'destroy'])->whereUuid('record')->defaults('content_type', $resource)->name('admin.'.$resource.'.destroy');
        }
    });
});
