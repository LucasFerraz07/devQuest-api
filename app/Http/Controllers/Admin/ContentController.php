<?php

namespace App\Http\Controllers\Admin;

use App\Builder\ReturnApi;
use App\Enums\ContentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentRequest;
use App\Http\Requests\Admin\ReorderContentRequest;
use App\Http\Requests\Learning\IndexRequest;
use App\Http\Resources\Admin\ContentResource;
use App\Services\Learning\ContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function __construct(private ContentService $service) {}

    public function index(IndexRequest $request): JsonResponse
    {
        return ReturnApi::page($this->service->index($this->type($request), $request->validated()), ContentResource::class);
    }

    public function show(Request $request): JsonResponse
    {
        return ReturnApi::success(new ContentResource($this->service->find($this->type($request), $request->route('record'))));
    }

    public function store(ContentRequest $request): JsonResponse
    {
        return ReturnApi::success(new ContentResource($this->service->save($this->type($request), null, $request->validated(), $request->user())), '', 201);
    }

    public function update(ContentRequest $request): JsonResponse
    {
        return ReturnApi::success(new ContentResource($this->service->save($this->type($request), $request->route('record'), $request->validated(), $request->user())));
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->service->delete($this->type($request), $request->route('record'));

        return ReturnApi::success(null, 'Conteúdo excluído.');
    }

    public function reorder(ReorderContentRequest $request): JsonResponse
    {
        $this->service->reorder(ContentType::from($request->route('child_type')), $request->route('record'), $request->validated('ids'));

        return ReturnApi::success(null, 'Ordem atualizada.');
    }

    private function type(Request $request): ContentType
    {
        return ContentType::from($request->route('content_type'));
    }
}
