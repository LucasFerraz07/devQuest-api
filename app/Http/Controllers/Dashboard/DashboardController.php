<?php

namespace App\Http\Controllers\Dashboard;

use App\Builder\ReturnApi;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Learning\DashboardResource;
use App\Services\Learning\ProfileService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Painel')]
class DashboardController extends Controller
{
    public function __construct(private ProfileService $service) {}

    #[Endpoint(operationId: 'showDashboard', description: 'Retorna DashboardResource com XP, nível, progresso e próxima atividade do aluno. Requer autenticação JWT.')]
    public function show(Request $request): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: DashboardResource}
             */
            return ReturnApi::success(new DashboardResource($this->service->dashboard($request->user())));
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
