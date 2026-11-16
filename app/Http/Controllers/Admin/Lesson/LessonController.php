<?php

namespace App\Http\Controllers\Admin\Lesson;

use App\Builder\ReturnApi;
use App\Enums\ContentType;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentRequest;
use App\Http\Requests\Learning\IndexRequest;
use App\Http\Resources\Admin\ContentResource;
use App\Services\Learning\ContentService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Administração: aulas')]
class LessonController extends Controller
{
    public function __construct(private ContentService $service) {}

    #[Endpoint(operationId: 'adminLessonsIndex', description: 'Lista trilhas, etapas, aulas ou exercícios com paginação e filtros. Em 200, data contém ContentResource e meta contém a paginação. Requer autenticação JWT e perfil admin.')]
    public function index(IndexRequest $request): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: list<ContentResource>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
             */
            return ReturnApi::page($this->service->index(ContentType::LESSON, $request->validated()), ContentResource::class);
        } catch (ApiException $e) {
            /**
             * @status 409
             *
             * @body array{error: true, message: string, data: mixed}
             */
            return ReturnApi::error($e->getMessage(), $e->data, $e->getCode());
        }
    }

    #[Endpoint(operationId: 'adminLessonsShow', description: 'Retorna ContentResource conforme o tipo de conteúdo da rota, incluindo alternativas e testes nos exercícios. Requer autenticação JWT e perfil admin.')]
    public function show(Request $request): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: ContentResource}
             */
            return ReturnApi::success(new ContentResource($this->service->find(ContentType::LESSON, $request->route('record'))));
        } catch (ApiException $e) {
            /**
             * @status 409
             *
             * @body array{error: true, message: string, data: mixed}
             */
            return ReturnApi::error($e->getMessage(), $e->data, $e->getCode());
        }
    }

    #[Endpoint(operationId: 'adminLessonsStore', description: 'Cria o conteúdo e retorna ContentResource em 201. Publicações devem atender às regras de integridade da trilha. Requer autenticação JWT e perfil admin.')]
    public function store(ContentRequest $request): JsonResponse
    {
        try {
            /**
             * @status 201
             *
             * @body array{error: false, message: string, data: ContentResource}
             */
            return ReturnApi::success(new ContentResource($this->service->save(ContentType::LESSON, null, $request->validated(), $request->user())), '', 201);
        } catch (ApiException $e) {
            /**
             * @status 409
             *
             * @body array{error: true, message: string, data: mixed}
             */
            return ReturnApi::error($e->getMessage(), $e->data, $e->getCode());
        }
    }

    #[Endpoint(operationId: 'adminLessonsUpdate', description: 'Atualiza o conteúdo e retorna ContentResource. Alterações estruturais em trilhas já iniciadas retornam 409. Requer autenticação JWT e perfil admin.')]
    public function update(ContentRequest $request): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: ContentResource}
             */
            return ReturnApi::success(new ContentResource($this->service->save(ContentType::LESSON, $request->route('record'), $request->validated(), $request->user())));
        } catch (ApiException $e) {
            /**
             * @status 409
             *
             * @body array{error: true, message: string, data: mixed}
             */
            return ReturnApi::error($e->getMessage(), $e->data, $e->getCode());
        }
    }

    #[Endpoint(operationId: 'adminLessonsDestroy', description: 'Exclui o conteúdo e retorna data null. Conteúdo de trilhas já iniciadas não pode ser excluído (409). Requer autenticação JWT e perfil admin.')]
    public function destroy(Request $request): JsonResponse
    {
        try {
            $this->service->delete(ContentType::LESSON, $request->route('record'));

            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: null}
             */
            return ReturnApi::success(null, 'Conteúdo excluído.');
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
