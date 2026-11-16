<?php

namespace App\Http\Controllers\LessonCompletion;

use App\Builder\ReturnApi;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Learning\ProgressResultResource;
use App\Models\Lesson;
use App\Services\Learning\ProgressService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Conclusão de aulas')]
class LessonCompletionController extends Controller
{
    public function __construct(private ProgressService $progress) {}

    #[Endpoint(operationId: 'completeLesson', description: 'Marca a aula como concluída de forma idempotente e retorna ProgressResultResource com progresso e novas conquistas. Requer autenticação JWT e acesso à aula.')]
    public function update(Request $request, Lesson $lesson): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: ProgressResultResource}
             */
            return ReturnApi::success(new ProgressResultResource($this->progress->completeLesson($lesson, $request->user())));
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
