<?php

namespace App\Http\Controllers\LearningPath;

use App\Builder\ReturnApi;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\IndexRequest;
use App\Http\Resources\Learning\LearningPathResource;
use App\Models\LearningPath;
use App\Services\Learning\JourneyService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Trilhas de aprendizado')]
class LearningPathController extends Controller
{
    public function __construct(private JourneyService $journey) {}

    #[Endpoint(operationId: 'indexLearningPaths', description: 'Lista as trilhas publicadas com paginação. Em 200, data contém LearningPathResource e meta contém a paginação. Requer autenticação JWT.')]
    public function index(IndexRequest $request): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: list<LearningPathResource>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
             */
            return ReturnApi::page($this->journey->catalogue($request->user(), $request->validated()), LearningPathResource::class);
        } catch (ApiException $e) {
            /**
             * @status 403
             *
             * @body array{error: true, message: string, data: mixed}
             */
            return ReturnApi::error($e->getMessage(), $e->data, $e->getCode());
        }
    }

    #[Endpoint(operationId: 'showLearningPath', description: 'Retorna LearningPathResource com etapas e disponibilidade para o aluno. Requer autenticação JWT e acesso à trilha publicada.')]
    public function show(Request $request, LearningPath $learningPath): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: LearningPathResource}
             */
            return ReturnApi::success(new LearningPathResource($this->journey->path($learningPath, $request->user())));
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
