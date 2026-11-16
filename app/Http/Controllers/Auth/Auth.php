<?php

namespace App\Http\Controllers\Auth;

use App\Builder\ReturnApi;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\UserResource;
use App\Services\Auth\AuthService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Autenticação')]
class Auth extends Controller
{
    public function __construct(private readonly AuthService $service) {}

    #[Endpoint(operationId: 'registerUser', description: 'Cadastra um aluno e emite os tokens de autenticação. Em 201, data contém o usuário e os dados de validade do token. Acesso público.')]
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            /**
             * @status 201
             *
             * @body array{error: false, message: string, data: array{user: UserResource, token: string, token_type: string, expires_in: int, refresh_expires_in: int}}
             */
            return $this->authenticated($this->service->register($request->validated()), 201);
        } catch (ApiException $e) {
            /**
             * @status 401
             *
             * @body array{error: true, message: string, data: mixed}
             */
            return ReturnApi::error($e->getMessage(), $e->data, $e->getCode());
        }
    }

    #[Endpoint(operationId: 'loginUser', description: 'Valida as credenciais e emite um token JWT. Em 200, data contém o usuário e os dados de validade do token. Acesso público.')]
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: array{user: UserResource, token: string, token_type: string, expires_in: int, refresh_expires_in: int}}
             */
            return $this->authenticated($this->service->login($request->validated()));
        } catch (ApiException $e) {
            /**
             * @status 401
             *
             * @body array{error: true, message: string, data: mixed}
             */
            return ReturnApi::error($e->getMessage(), $e->data, $e->getCode());
        }
    }

    #[Endpoint(operationId: 'getAuthenticatedUser', description: 'Retorna UserResource em data. Requer autenticação JWT.')]
    public function me(): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: UserResource}
             */
            return ReturnApi::success(new UserResource($this->service->me()));
        } catch (ApiException $e) {
            /**
             * @status 401
             *
             * @body array{error: true, message: string, data: mixed}
             */
            return ReturnApi::error($e->getMessage(), $e->data, $e->getCode());
        }
    }

    #[Endpoint(operationId: 'refreshToken', description: 'Renova o JWT dentro da janela de renovação e invalida o token anterior. Envie o token no header Authorization, inclusive se expirado.')]
    public function refresh(Request $request): JsonResponse
    {
        try {
            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: array{token: string, token_type: string, expires_in: int, refresh_expires_in: int}}
             */
            return ReturnApi::success($this->service->refresh((string) $request->bearerToken()));
        } catch (ApiException $e) {
            /**
             * @status 401
             *
             * @body array{error: true, message: string, data: mixed}
             */
            return ReturnApi::error($e->getMessage(), $e->data, $e->getCode());
        }
    }

    #[Endpoint(operationId: 'logoutUser', description: 'Invalida o token JWT atual. Em 200, data é null. Requer autenticação JWT.')]
    public function logout(): JsonResponse
    {
        try {
            $this->service->logout();

            /**
             * @status 200
             *
             * @body array{error: false, message: string, data: null}
             */
            return ReturnApi::success(null, 'Logout realizado.');
        } catch (ApiException $e) {
            /**
             * @status 401
             *
             * @body array{error: true, message: string, data: mixed}
             */
            return ReturnApi::error($e->getMessage(), $e->data, $e->getCode());
        }
    }

    private function authenticated(array $data, int $status = 200): JsonResponse
    {
        $data['user'] = new UserResource($data['user']);

        return ReturnApi::success($data, '', $status);
    }
}
