<?php

namespace App\Services\Auth;

use App\Enums\UserType;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthService
{
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserType::STUDENT,
            ]);

            return ['user' => $user->refresh(), ...$this->token(auth('api')->login($user))];
        });
    }

    public function login(array $data): array
    {
        $token = auth('api')->attempt($data);
        if (! $token) {
            throw new ApiException('Usuário ou senha inválido.', 401);
        }

        return ['user' => auth('api')->user(), ...$this->token($token)];
    }

    public function me(): User
    {
        $user = auth('api')->user();
        if (! $user) {
            throw new ApiException('Autenticação inválida ou expirada.', 401);
        }

        return $user;
    }

    public function logout(): void
    {
        try {
            auth('api')->logout();
        } catch (JWTException $e) {
            throw new ApiException('Autenticação inválida ou expirada.', 401, previous: $e);
        }
    }

    public function refresh(string $currentToken): array
    {
        if ($currentToken === '') {
            throw new ApiException('Token não encontrado.', 401);
        }
        try {
            $token = auth('api')->forgetUser()->setToken($currentToken)->refresh();
            // Refresh validates signature, blacklist and refresh window before subject lookup.
            $user = auth('api')->setToken($token)->user();
            if (! $user) {
                auth('api')->invalidate();
                throw new ApiException('Usuário não encontrado.', 401);
            }

            return $this->token($token);
        } catch (JWTException $e) {
            throw new ApiException('Autenticação inválida ou expirada.', 401, previous: $e);
        } finally {
            app('tymon.jwt.manager')->setRefreshFlow(false);
        }
    }

    private function token(string $token): array
    {
        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
            'refresh_expires_in' => (int) config('jwt.refresh_ttl') * 60,
        ];
    }
}
