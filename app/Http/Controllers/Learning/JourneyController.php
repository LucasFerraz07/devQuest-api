<?php

namespace App\Http\Controllers\Learning;

use App\Builder\ReturnApi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\IndexRequest;
use App\Http\Requests\Learning\SubmitExerciseRequest;
use App\Http\Resources\Learning\ExerciseResource;
use App\Http\Resources\Learning\LearningPathResource;
use App\Http\Resources\Learning\LessonResource;
use App\Http\Resources\Learning\ProgressResultResource;
use App\Http\Resources\Learning\StageResource;
use App\Models\Exercise;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Stage;
use App\Services\Learning\JourneyService;
use App\Services\Learning\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JourneyController extends Controller
{
    public function __construct(private JourneyService $journey, private ProgressService $progress) {}

    public function index(IndexRequest $request): JsonResponse
    {
        return ReturnApi::page($this->journey->catalogue($request->user(), $request->validated()), LearningPathResource::class);
    }

    public function path(Request $request, LearningPath $learningPath): JsonResponse
    {
        return ReturnApi::success(new LearningPathResource($this->journey->path($learningPath, $request->user())));
    }

    public function stage(Request $request, Stage $stage): JsonResponse
    {
        return ReturnApi::success(new StageResource($this->journey->stage($stage, $request->user())));
    }

    public function start(Request $request, Stage $stage): JsonResponse
    {
        return ReturnApi::success(new StageResource($this->progress->start($stage, $request->user())));
    }

    public function lesson(Request $request, Lesson $lesson): JsonResponse
    {
        return ReturnApi::success(new LessonResource($this->journey->lesson($lesson, $request->user())));
    }

    public function completeLesson(Request $request, Lesson $lesson): JsonResponse
    {
        return ReturnApi::success(new ProgressResultResource($this->progress->completeLesson($lesson, $request->user())));
    }

    public function exercise(Request $request, Exercise $exercise): JsonResponse
    {
        return ReturnApi::success(new ExerciseResource($this->journey->exercise($exercise, $request->user())));
    }

    public function submit(SubmitExerciseRequest $request, Exercise $exercise): JsonResponse
    {
        return ReturnApi::success(new ProgressResultResource($this->progress->submit($exercise, $request->user(), $request->validated('selected_option_id'))), '', 201);
    }
}
