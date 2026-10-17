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
use Spatie\Permission\Exceptions\UnauthorizedException;
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
                $e->getMessage() ?: '请先登录。',
                401,
                null,
                401,
            ),
            // spatie 的权限中间件抛的是英文默认文案，统一成中文再返回
            $e instanceof UnauthorizedException => $this->errorResponse(
                '没有该操作权限。',
                403,
                null,
                403,
            ),
            $e instanceof AuthorizationException => $this->errorResponse(
                $e->getMessage() ?: '没有该操作权限。',
                403,
                null,
                403,
            ),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => $this->errorResponse(
                '请求的资源不存在。',
                404,
                null,
                404,
            ),
            $e instanceof ThrottleRequestsException => $this->errorResponse(
                '请求过于频繁，请稍后再试。',
                429,
                null,
                429,
            ),
            $e instanceof HttpExceptionInterface => $this->errorResponse(
                $e->getMessage() ?: '请求处理失败。',
                $e->getStatusCode(),
                null,
                $e->getStatusCode(),
            ),
            default => $this->errorResponse(
                config('app.debug') ? $e->getMessage() : '服务器内部错误。',
                500,
                null,
                500,
            ),
        };
    }
}
