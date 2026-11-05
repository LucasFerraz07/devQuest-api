<?php

namespace App\Builder;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnApi
{
    public static function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json(['error' => false, 'message' => $message, 'data' => $data], $status);
    }

    /** @param class-string<JsonResource> $resource */
    public static function page(LengthAwarePaginator $page, string $resource): JsonResponse
    {
        return response()->json([
            'error' => false,
            'message' => '',
            'data' => $resource::collection($page->getCollection())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public static function error(string $message = '', mixed $data = null, int $status = 400, ?array $errors = null): JsonResponse
    {
        $body = ['error' => true, 'message' => $message, 'data' => $data];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }
}
