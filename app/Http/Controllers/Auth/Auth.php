<?php

namespace App\Http\Controllers\Auth;

use App\Builder\ReturnApi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Auth extends Controller
{
    public function __construct(private readonly AuthService $service) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->authenticated($this->service->register($request->validated()), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->authenticated($this->service->login($request->validated()));
    }

    public function me(): JsonResponse
    {
        return ReturnApi::success(new UserResource(auth('api')->user()));
    }

    public function refresh(Request $request): JsonResponse
    {
        return ReturnApi::success($this->service->refresh((string) $request->bearerToken()));
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return ReturnApi::success(null, 'Logout realizado.');
    }

    private function authenticated(array $data, int $status = 200): JsonResponse
    {
        $data['user'] = new UserResource($data['user']);

        return ReturnApi::success($data, '', $status);
    }
}
