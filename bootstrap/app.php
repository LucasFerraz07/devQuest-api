<?php

use App\Builder\ReturnApi;
use App\Http\Middleware\JwtMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tymon\JWTAuth\Exceptions\JWTException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: fn () => Route::prefix('api')->middleware('api')->group(base_path('routes/api/index.php')),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['auth.api' => JwtMiddleware::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ReturnApi::error('Confira os campos informados.', null, 422, $e->errors());
            }
        });
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ReturnApi::error('Você não tem permissão para acessar este recurso.', null, 403);
            }
        });
        $exceptions->render(function (AuthenticationException|JWTException $e, Request $request) {
            if ($request->is('api/*')) {
                return ReturnApi::error('Autenticação inválida ou expirada.', null, 401);
            }
        });
        $exceptions->render(function (UniqueConstraintViolationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ReturnApi::error('Já existe um registro com estes dados.', null, 409);
            }
        });
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                $message = match ($e->getStatusCode()) {
                    404 => 'Recurso não encontrado.',
                    429 => 'Muitas tentativas. Aguarde antes de tentar novamente.',
                    default => $e->getMessage() ?: 'Não foi possível processar a solicitação.',
                };

                return ReturnApi::error($message, null, $e->getStatusCode())->withHeaders($e->getHeaders());
            }
        });
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return ReturnApi::error('Não foi possível processar a solicitação.', null, 500);
            }
        });
    })->create();
