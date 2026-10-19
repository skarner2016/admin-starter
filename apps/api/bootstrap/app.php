<?php

use App\Exceptions\ApiExceptionHandler;
use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 纯 API 应用没有 login 路由，而 Laravel 12 的 withMiddleware 默认注册了
        // redirectGuestsTo(fn () => route('login'))；未认证的「非 JSON」请求会在中间件里
        // 直接抛 RouteNotFoundException（500）。这里去掉重定向目标，
        // 让未认证异常落到 withExceptions 的统一响应包里返回 401。
        $middleware->redirectGuestsTo(fn () => null);

        // spatie 的权限中间件在 routes 里以 permission:xxx 使用
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            // 账号状态兜底：必须排在 auth:sanctum 之后（否则 $request->user() 还是 null）
            'account.active' => EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // api/* 一律按 JSON 处理，避免走「重定向到登录页」的分支
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 只接管 API 路由的异常，其余交回框架默认处理
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return app(ApiExceptionHandler::class)->render($e, $request);
        });
    })->create();
