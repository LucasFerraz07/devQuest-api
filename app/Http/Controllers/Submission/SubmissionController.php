<?php

namespace App\Http\Controllers\Submission;

use App\Builder\ReturnApi;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\IndexRequest;
use App\Http\Requests\Learning\SubmitExerciseRequest;
use App\Http\Resources\Learning\ProgressResultResource;
use App\Http\Resources\Learning\SubmissionResource;
use App\Models\Exercise;
use App\Services\Learning\ProfileService;
use App\Services\Learning\ProgressService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Submissões')]
class SubmissionController extends Controller
{
    public function __construct(private ProfileService $service, private ProgressService $progress) {}

    #[Endpoint(operationId: 'indexSubmissions', description: 'Lista as respostas do aluno com paginação e filtros por exercício e resultado. Em 200, data contém SubmissionResource. Requer autenticação JWT.')]
    public function index(IndexRequest $request): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: list<SubmissionResource>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
             */
            return ReturnApi::page($this->service->submissions($request->user(), $request->validated()), SubmissionResource::class);
        } catch (ApiException $e) {
            /**
             * @status 403
             *
             * @body array{error: true, message: string, data: mixed}
             */
            return ReturnApi::error($e->getMessage(), $e->data, $e->getCode());
        }
    }

    #[Endpoint(operationId: 'submitExercise', description: 'Registra a resposta e retorna ProgressResultResource em 201. Apenas o primeiro acerto concede XP. Requer autenticação JWT e uma alternativa pertencente ao exercício.')]
    public function store(SubmitExerciseRequest $request, Exercise $exercise): JsonResponse
    {
        try {
            /**
             * @status 201
             *
             * @body array{error: false, message: string, data: ProgressResultResource}
             */
            return ReturnApi::success(new ProgressResultResource($this->progress->submit($exercise, $request->user(), $request->validated('selected_option_id'))), '', 201);
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
