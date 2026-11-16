<?php

namespace App\Http\Controllers\Stage;

use App\Builder\ReturnApi;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Learning\StageResource;
use App\Models\Stage;
use App\Services\Learning\JourneyService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Etapas')]
class StageController extends Controller
{
    public function __construct(private JourneyService $journey) {}

    #[Endpoint(operationId: 'showStage', description: 'Retorna StageResource com aulas, exercícios e progresso. Requer autenticação JWT e conclusão da etapa anterior, quando houver.')]
    public function show(Request $request, Stage $stage): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: StageResource}
             */
            return ReturnApi::success(new StageResource($this->journey->stage($stage, $request->user())));
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
