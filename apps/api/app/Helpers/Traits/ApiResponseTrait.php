<?php

namespace App\Helpers\Traits;

use Illuminate\Http\JsonResponse;

/**
 * 统一响应包：{ code, data, message, error }
 * 前端 defaultResponseInterceptor 以 code === 0 判定成功。
 */
trait ApiResponseTrait
{
    protected function successResponse(mixed $data = null, string $message = 'ok', int $code = 0): JsonResponse
    {
        return new JsonResponse([
            'code' => $code,
            'data' => $data,
            'message' => $message,
            'error' => null,
        ]);
    }

    protected function errorResponse(
        string $message,
        int $code = -1,
        mixed $error = null,
        int $status = 200,
    ): JsonResponse {
        return new JsonResponse([
            'code' => $code,
            'data' => null,
            'message' => $message,
            'error' => $error ?? $message,
        ], $status);
    }

    protected function paginatedResponse(mixed $items, int $total, string $message = 'ok'): JsonResponse
    {
        return $this->successResponse(['items' => $items, 'total' => $total], $message);
    }
}
