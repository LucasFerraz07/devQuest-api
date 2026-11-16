<?php

namespace App\Http\Controllers\Exercise;

use App\Builder\ReturnApi;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Learning\ExerciseResource;
use App\Models\Exercise;
use App\Services\Learning\JourneyService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Exercícios')]
class ExerciseController extends Controller
{
    public function __construct(private JourneyService $journey) {}

    #[Endpoint(operationId: 'showExercise', description: 'Retorna ExerciseResource sem expor a alternativa correta. A explicação fica disponível após um acerto. Requer autenticação JWT e acesso à etapa.')]
    public function show(Request $request, Exercise $exercise): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: ExerciseResource}
             */
            return ReturnApi::success(new ExerciseResource($this->journey->exercise($exercise, $request->user())));
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
