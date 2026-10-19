<?php

namespace App\Http\Middleware;

use App\Models\AdminUserModel;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 已认证请求再校验账号状态：停用账号的 access token 立即失效。
 *
 * 正常的停用入口（AdminUserManageService）会吊销该用户全部令牌，但若令牌残留
 * （例如直接改库、或吊销失败），Token 本身仍然有效。这里补一道兜底，保证
 * 「停用 = 立即失效」不依赖某一个入口。
 *
 * 必须挂在 auth:sanctum 之后（见 routes/api.php），此时 $request->user() 才可用。
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof AdminUserModel && (int) $user->status !== 1) {
            throw new AuthenticationException('账号已被停用。');
        }

        return $next($request);
    }
}
