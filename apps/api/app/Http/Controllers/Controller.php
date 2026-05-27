<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

abstract class Controller
{
    use AuthorizesRequests;

    protected function ok(mixed $data, Request $request, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => ['request_id' => $request->header('X-Request-Id', (string) str()->uuid())],
        ], $status);
    }

    protected function paginated(LengthAwarePaginator $page, string $resourceClass, Request $request): JsonResponse
    {
        return response()->json([
            'data' => $resourceClass::collection($page->items()),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'has_more' => $page->hasMorePages(),
                'request_id' => $request->header('X-Request-Id', (string) str()->uuid()),
            ],
        ]);
    }

    protected function error(string $code, string $message, Request $request, int $status): JsonResponse
    {
        return response()->json([
            'error' => ['code' => $code, 'message' => $message],
            'meta' => ['request_id' => $request->header('X-Request-Id', (string) str()->uuid())],
        ], $status);
    }
}
