<?php

namespace App\Exceptions;

use App\Helpers\Traits\ApiResponseTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * 复用 ApiResponseTrait，使全局异常也符合统一响应包。
 * withExceptions 闭包不是控制器、无法直接 use trait，故由此类承担。
 */
class ApiExceptionHandler
{
    use ApiResponseTrait;

    public function render(Throwable $e, Request $request): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => $this->errorResponse(
                $e->getMessage(),
                422,
                $e->errors(),
                422,
            ),
            $e instanceof AuthenticationException => $this->errorResponse(
                $e->getMessage() ?: 'Unauthenticated.',
                401,
                null,
                401,
            ),
            $e instanceof AuthorizationException => $this->errorResponse(
                $e->getMessage() ?: 'Forbidden.',
                403,
                null,
                403,
            ),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => $this->errorResponse(
                'Resource not found.',
                404,
                null,
                404,
            ),
            $e instanceof ThrottleRequestsException => $this->errorResponse(
                'Too many requests.',
                429,
                null,
                429,
            ),
            $e instanceof HttpExceptionInterface => $this->errorResponse(
                $e->getMessage() ?: 'Http error.',
                $e->getStatusCode(),
                null,
                $e->getStatusCode(),
            ),
            default => $this->errorResponse(
                config('app.debug') ? $e->getMessage() : 'Server error.',
                500,
                null,
                500,
            ),
        };
    }
}
