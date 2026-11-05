<?php

use App\Enums\ContentType;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Learning\JourneyController;
use App\Http\Controllers\Learning\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(base_path('routes/api/auth.php'));

Route::middleware('auth.api')->group(function () {
    Route::get('learning-paths', [JourneyController::class, 'index']);
    Route::get('learning-paths/{learningPath}', [JourneyController::class, 'path'])->whereUuid('learningPath');
    Route::get('stages/{stage}', [JourneyController::class, 'stage'])->whereUuid('stage');
    Route::post('stages/{stage}/start', [JourneyController::class, 'start'])->whereUuid('stage');
    Route::get('lessons/{lesson}', [JourneyController::class, 'lesson'])->whereUuid('lesson');
    Route::put('lessons/{lesson}/completion', [JourneyController::class, 'completeLesson'])->whereUuid('lesson');
    Route::get('exercises/{exercise}', [JourneyController::class, 'exercise'])->whereUuid('exercise');
    Route::post('exercises/{exercise}/submissions', [JourneyController::class, 'submit'])->whereUuid('exercise');
    Route::get('me/dashboard', [ProfileController::class, 'dashboard']);
    Route::get('me/submissions', [ProfileController::class, 'submissions']);
    Route::get('me/achievements', [ProfileController::class, 'achievements']);

    Route::prefix('admin')->middleware('can:manage-content')->group(function () {
        Route::put('learning-paths/{record}/stages/order', [ContentController::class, 'reorder'])->whereUuid('record')->defaults('child_type', 'stages');
        Route::put('stages/{record}/lessons/order', [ContentController::class, 'reorder'])->whereUuid('record')->defaults('child_type', 'lessons');
        Route::put('stages/{record}/exercises/order', [ContentController::class, 'reorder'])->whereUuid('record')->defaults('child_type', 'exercises');
        foreach (ContentType::cases() as $type) {
            Route::get($type->value, [ContentController::class, 'index'])->defaults('content_type', $type->value);
            Route::post($type->value, [ContentController::class, 'store'])->defaults('content_type', $type->value);
            Route::get($type->value.'/{record}', [ContentController::class, 'show'])->whereUuid('record')->defaults('content_type', $type->value);
            Route::patch($type->value.'/{record}', [ContentController::class, 'update'])->whereUuid('record')->defaults('content_type', $type->value);
            Route::delete($type->value.'/{record}', [ContentController::class, 'destroy'])->whereUuid('record')->defaults('content_type', $type->value);
        }
    });
});
