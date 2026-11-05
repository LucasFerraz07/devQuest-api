<?php

namespace App\Http\Controllers\Learning;

use App\Builder\ReturnApi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\IndexRequest;
use App\Http\Resources\Learning\AchievementResource;
use App\Http\Resources\Learning\DashboardResource;
use App\Http\Resources\Learning\SubmissionResource;
use App\Services\Learning\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private ProfileService $service) {}

    public function dashboard(Request $request): JsonResponse
    {
        return ReturnApi::success(new DashboardResource($this->service->dashboard($request->user())));
    }

    public function submissions(IndexRequest $request): JsonResponse
    {
        return ReturnApi::page($this->service->submissions($request->user(), $request->validated()), SubmissionResource::class);
    }

    public function achievements(IndexRequest $request): JsonResponse
    {
        return ReturnApi::page($this->service->achievements($request->user(), $request->validated()), AchievementResource::class);
    }
}
