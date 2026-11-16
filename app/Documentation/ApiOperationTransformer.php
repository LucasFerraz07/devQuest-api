<?php

namespace App\Documentation;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

class ApiOperationTransformer implements OperationTransformer
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $middleware = $routeInfo->route->gatherMiddleware();
        $authenticated = in_array('auth.api', $middleware, true) || $routeInfo->route->uri() === 'api/auth/refresh';
        if (! $authenticated) {
            $operation->security = [];
        }

        // Scramble infers Laravel's default errors; the application uses ReturnApi instead.
        $codes = [500];
        $successes = [];
        foreach ($operation->responses ?? [] as $response) {
            $resolved = $response instanceof Reference ? $response->resolve() : $response;
            if (is_numeric($resolved->code) && (int) $resolved->code >= 400) {
                $codes[] = (int) $resolved->code;
            } else {
                $successes[] = $response;
            }
        }
        $operation->responses = $successes;
        if ($authenticated) {
            $codes[] = 401;
        }
        if ($routeInfo->route->parameterNames()) {
            $codes[] = 404;
        }
        if ($routeInfo->route->uri() === 'api/auth/register') {
            $codes[] = 409;
        }

        foreach (array_unique($codes) as $code) {
            $operation->addResponse($this->errorResponse($code));
        }
    }

    private function errorResponse(int $code): Response
    {
        $error = (new BooleanType)->enum([true]);
        $body = (new ObjectType)
            ->addProperty('error', $error)
            ->addProperty('message', new StringType)
            ->addProperty('data', new MixedType)
            ->setRequired(['error', 'message', 'data']);
        if ($code === 422) {
            $body->addProperty('errors', (new ObjectType)->additionalProperties(
                (new ArrayType)->setItems(new StringType),
            ))->addRequired(['errors']);
        }

        $response = Response::make($code)
            ->setDescription(match ($code) {
                401 => 'Autenticação inválida ou expirada.',
                403 => 'Você não tem permissão para acessar este recurso.',
                404 => 'Recurso não encontrado.',
                409 => 'Conflito com o estado atual ou registro duplicado.',
                422 => 'Confira os campos informados.',
                429 => 'Muitas tentativas. Aguarde antes de tentar novamente.',
                default => 'Não foi possível processar a solicitação.',
            })
            ->setContent('application/json', Schema::fromType($body));
        if ($code === 429) {
            $response->addHeader('Retry-After', new Header(
                description: 'Tempo de espera em segundos.',
                schema: Schema::fromType(new IntegerType),
            ));
        }

        return $response;
    }
}
