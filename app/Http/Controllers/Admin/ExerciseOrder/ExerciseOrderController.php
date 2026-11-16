<?php

namespace App\Http\Controllers\Admin\ExerciseOrder;

use App\Builder\ReturnApi;
use App\Enums\ContentType;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderContentRequest;
use App\Services\Learning\ContentService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Administração: ordem de exercícios')]
class ExerciseOrderController extends Controller
{
    public function __construct(private ContentService $service) {}

    #[Endpoint(operationId: 'adminStagesExercisesReorder', description: 'Reordena todos os itens do agrupamento de forma atômica. Em 200, data é null. Trilhas iniciadas retornam 409; conjuntos incompletos ou de outro agrupamento retornam 422. Requer autenticação JWT e perfil admin.')]
    public function update(ReorderContentRequest $request): JsonResponse
    {
        try {
            $this->service->reorder(ContentType::EXERCISE, $request->route('record'), $request->validated('ids'));

            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: null}
             */
            return ReturnApi::success(null, 'Ordem atualizada.');
        } catch (ApiException $e) {
            /**
             * @status 409
             *
             * @body array{error: true, message: string, data: mixed}
             */
            return ReturnApi::error($e->getMessage(), $e->data, $e->getCode());
        }
    }
}
