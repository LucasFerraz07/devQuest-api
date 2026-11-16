<?php

namespace App\Http\Controllers\Lesson;

use App\Builder\ReturnApi;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Learning\LessonResource;
use App\Models\Lesson;
use App\Services\Learning\JourneyService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Aulas')]
class LessonController extends Controller
{
    public function __construct(private JourneyService $journey) {}

    #[Endpoint(operationId: 'showLesson', description: 'Retorna LessonResource com conteúdo e navegação entre aulas. Requer autenticação JWT e acesso à etapa da aula.')]
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: LessonResource}
             */
            return ReturnApi::success(new LessonResource($this->journey->lesson($lesson, $request->user())));
        } catch (ApiException $e) {
            /**
             * @status 403
             *
             * @body array{error: true, message: string, data: mixed}
             */
            return ReturnApi::error($e->getMessage(), $e->data, $e->getCode());
        }
    }
}
