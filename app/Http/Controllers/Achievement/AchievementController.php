<?php

namespace App\Http\Controllers\Achievement;

use App\Builder\ReturnApi;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\IndexRequest;
use App\Http\Resources\Learning\AchievementResource;
use App\Services\Learning\ProfileService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Conquistas')]
class AchievementController extends Controller
{
    public function __construct(private ProfileService $service) {}

    #[Endpoint(operationId: 'indexAchievements', description: 'Lista as conquistas do aluno com paginação. Em 200, data contém AchievementResource. Requer autenticação JWT.')]
    public function index(IndexRequest $request): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: list<AchievementResource>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
             */
            return ReturnApi::page($this->service->achievements($request->user(), $request->validated()), AchievementResource::class);
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
