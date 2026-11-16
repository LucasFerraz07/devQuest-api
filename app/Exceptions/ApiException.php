<?php

namespace App\Exceptions;

use App\Builder\ReturnApi;
use Illuminate\Http\JsonResponse;
use Throwable;

class ApiException extends \Exception
{
    public ?array $data;

    public function __construct(string $message = 'Erro inesperado', int $code = 500, ?array $data = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    public function render(): JsonResponse
    {
        return ReturnApi::error($this->getMessage(), $this->data, $this->getCode());
    }
}
