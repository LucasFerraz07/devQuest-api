<?php

namespace App\Http\Controllers\StageProgress;

use App\Builder\ReturnApi;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Learning\StageResource;
use App\Models\Stage;
use App\Services\Learning\ProgressService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Progresso de etapas')]
class StageProgressController extends Controller
{
    public function __construct(private ProgressService $progress) {}

    #[Endpoint(operationId: 'startStage', description: 'Inicia uma etapa de forma idempotente e retorna StageResource. Requer autenticação JWT e acesso à etapa.')]
    public function store(Request $request, Stage $stage): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: StageResource}
             */
            return ReturnApi::success(new StageResource($this->progress->start($stage, $request->user())));
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
