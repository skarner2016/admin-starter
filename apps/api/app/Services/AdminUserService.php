<?php

namespace App\Services;

use App\Exceptions\AccountDisabledException;
use App\Exceptions\AccountLockedException;
use App\Models\AdminUserModel;
use App\Models\LoginLogModel;
use App\Models\PersonalAccessTokenModel;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class AdminUserService
{
    public function isSuper(AdminUserModel $user): bool
    {
        return $user->hasRole(self::SUPER_ROLE);
    }

    public const SUPER_ROLE = 'super';

    public const ACCESS_TTL_MINUTES = 60;

    public const REFRESH_TTL_DAYS = 7;

    public const COOKIE_NAME = 'vben_refresh_token';

    public const MAX_FAILED_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    public const HOME_PATH = '/dashboard/workspace';

    public const ABILITY_ACCESS = 'access';

    public const ABILITY_REFRESH = 'refresh';

    /**
     * @return array{user: AdminUserModel, accessToken: string, refreshToken: string}
     */
    public function attemptLogin(
        string $username,
        string $password,
        ?string $ip,
        ?string $userAgent,
    ): array {
        if ($this->isLocked($username)) {
            $this->writeLog($username, null, false, 'locked', $ip, $userAgent);

            throw new AccountLockedException;
        }

        $user = AdminUserModel::query()->where('username', $username)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->writeLog($username, $user?->id, false, 'invalid_credentials', $ip, $userAgent);

            throw new AuthenticationException('Invalid credentials.');
        }

        if ((int) $user->status !== 1) {
            $this->writeLog($username, $user->id, false, 'disabled', $ip, $userAgent);

            throw new AccountDisabledException;
        }

        $this->writeLog($username, $user->id, true, null, $ip, $userAgent);

        return ['user' => $user, ...$this->issueTokens($user)];
    }

    /**
     * @return array{accessToken: string, refreshToken: string}
     */
    public function issueTokens(AdminUserModel $user): array
    {
        $access = $user->createToken(
            self::ABILITY_ACCESS,
            [self::ABILITY_ACCESS],
            now()->addMinutes(self::ACCESS_TTL_MINUTES),
        );

        $refresh = $user->createToken(
            self::ABILITY_REFRESH,
            [self::ABILITY_REFRESH],
            now()->addDays(self::REFRESH_TTL_DAYS),
        );

        return [
            'accessToken' => $access->plainTextToken,
            'refreshToken' => $refresh->plainTextToken,
        ];
    }

    /**
     * 校验 refresh token 并轮换（旧 refresh 立即失效，防重放）。
     *
     * @return array{accessToken: string, refreshToken: string}
     */
    public function refresh(string $refreshToken): array
    {
        $token = PersonalAccessTokenModel::findToken($refreshToken);

        if (! $token
            || ! $token->can(self::ABILITY_REFRESH)
            || ($token->expires_at && $token->expires_at->isPast())
        ) {
            throw new AuthenticationException('Invalid refresh token.');
        }

        $user = $token->tokenable;

        if (! $user instanceof AdminUserModel) {
            throw new AuthenticationException('Invalid refresh token.');
        }

        $token->delete();

        return $this->issueTokens($user);
    }

    public function revokeRefresh(string $refreshToken): void
    {
        PersonalAccessTokenModel::findToken($refreshToken)?->delete();
    }

    /**
     * 吊销某用户全部 refresh token。
     */
    public function revokeAllRefreshTokens(AdminUserModel $user): int
    {
        return $user->tokens()
            ->where('abilities', 'like', '%'.self::ABILITY_REFRESH.'%')
            ->delete();
    }

    /**
     * 吊销该用户全部令牌（access + refresh）。
     * 禁用账号 / 重置密码若只删 refresh，已签发的 access 仍会有效到过期（最长 60 分钟）。
     */
    public function revokeAllTokens(AdminUserModel $user): int
    {
        return $user->tokens()->delete();
    }

    /**
     * 锁定判定：窗口期内、上一次成功登录之后的连续失败次数达到阈值。
     */
    public function isLocked(string $username): bool
    {
        $lastSuccessAt = LoginLogModel::query()
            ->where('username', $username)
            ->where('success', true)
            ->max('created_at');

        return LoginLogModel::query()
            ->where('username', $username)
            ->where('success', false)
            ->where('created_at', '>=', now()->subMinutes(self::LOCK_MINUTES))
            ->when($lastSuccessAt, fn ($query) => $query->where('created_at', '>', $lastSuccessAt))
            ->count() >= self::MAX_FAILED_ATTEMPTS;
    }

    private function writeLog(
        string $username,
        ?int $userId,
        bool $success,
        ?string $reason,
        ?string $ip,
        ?string $userAgent,
    ): void {
        LoginLogModel::query()->create([
            'username' => $username,
            'admin_user_id' => $userId,
            'success' => $success,
            'reason' => $reason,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }
}
