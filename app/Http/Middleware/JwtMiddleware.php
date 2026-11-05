<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken()) {
            throw new AuthenticationException;
        }
        app('tymon.jwt.manager')->setRefreshFlow(false);
        if (! auth('api')->forgetUser()->setToken($request->bearerToken())->user()) {
            throw new AuthenticationException;
        }

        return $next($request);
    }
}
