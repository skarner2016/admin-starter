<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Traits\ApiResponseTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly AdminUserService $users) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->users->attemptLogin(
            $request->string('username')->toString(),
            $request->string('password')->toString(),
            $request->ip(),
            $request->userAgent(),
        );

        return $this->successResponse(['accessToken' => $result['accessToken']])
            ->withCookie($this->refreshCookie($result['refreshToken']));
    }

    /**
     * 前端用 baseRequestClient 调用且取 resp.data 直接当 token，
     * 故此处必须返回裸字符串，不能套统一响应包。
     */
    public function refresh(Request $request): Response
    {
        $refreshToken = $request->cookie(AdminUserService::COOKIE_NAME);

        if (! $refreshToken) {
            abort(401, '缺少刷新令牌。');
        }

        $tokens = $this->users->refresh($refreshToken);

        return response($tokens['accessToken'])
            ->header('Content-Type', 'text/plain')
            ->withCookie($this->refreshCookie($tokens['refreshToken']));
    }

    /**
     * 前端登出同样不携带 Bearer，只能靠 Cookie 定位并吊销 refresh token。
     */
    public function logout(Request $request): JsonResponse
    {
        $refreshToken = $request->cookie(AdminUserService::COOKIE_NAME);

        if ($refreshToken) {
            $this->users->revokeRefresh($refreshToken);
        }

        return $this->successResponse('')->withCookie($this->forgetRefreshCookie());
    }

    public function codes(Request $request): JsonResponse
    {
        // 前端无 super 豁免，super 必须显式拿到全量权限码；
        // 普通用户只统计启用角色贡献的权限码（见 AdminUserService::permissionCodesFor）
        return $this->successResponse($this->users->permissionCodesFor($request->user()));
    }

    private function refreshCookie(string $value): Cookie
    {
        $production = app()->isProduction();

        return cookie(
            AdminUserService::COOKIE_NAME,
            $value,
            AdminUserService::REFRESH_TTL_DAYS * 24 * 60,
            '/',
            null,
            $production,
            true,
            false,
            $production ? 'none' : 'lax',
        );
    }

    private function forgetRefreshCookie(): Cookie
    {
        return cookie()->forget(AdminUserService::COOKIE_NAME);
    }
}
