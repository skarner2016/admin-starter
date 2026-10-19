<?php

namespace App\Services;

use App\Exceptions\AccountDisabledException;
use App\Exceptions\AccountLockedException;
use App\Models\AdminUserModel;
use App\Models\LoginLogModel;
use App\Models\PersonalAccessTokenModel;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class AdminUserService
{
    public function isSuper(AdminUserModel $user): bool
    {
        return $user->hasRole(self::SUPER_ROLE);
    }

    /**
     * 当前用户的权限码。
     *
     * 只有**启用**状态的角色才贡献权限码——角色停用即等同于未授权。
     * super 仍然拿全量权限码（与 Gate::before 的放行保持一致）。
     *
     * @return array<int, string>
     */
    public function permissionCodesFor(AdminUserModel $user): array
    {
        if ($this->isSuper($user)) {
            return Permission::query()->pluck('name')->all();
        }

        return $user->roles()
            ->where('status', 1)
            ->with('permissions')
            ->get()
            ->flatMap(fn ($role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }

    public const SUPER_ROLE = 'super';

    public const ACCESS_TTL_MINUTES = 60;

    public const REFRESH_TTL_DAYS = 7;

    public const COOKIE_NAME = 'vben_refresh_token';

    /** 密码策略：≥8 位且同时包含大小写字母与数字。创建与重置共用，避免两条路径口径不一致 */
    public const PASSWORD_PATTERN = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/';

    public const PASSWORD_MIN_LENGTH = 8;

    public const MAX_FAILED_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    /** 锁定期间的尝试只记审计日志，不计入阈值（见 isLocked） */
    public const LOG_REASON_LOCKED = 'locked';

    public const LOG_REASON_INVALID = 'invalid_credentials';

    public const LOG_REASON_DISABLED = 'disabled';

    public const HOME_PATH = '/dashboard/workspace';

    public const ABILITY_ACCESS = 'access';

    public const ABILITY_REFRESH = 'refresh';

    /**
     * 自助修改资料（个人中心）：只允许改自己的昵称/头像/简介。
     */
    public function updateProfile(AdminUserModel $user, array $data): AdminUserModel
    {
        $user->update([
            'real_name' => $data['realName'],
            'avatar' => $data['avatar'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        return $user;
    }

    /**
     * 自助改密：保留当前会话的 access token，其余令牌（含所有 refresh）立即失效。
     * 无法识别当前令牌时（例如测试里的 TransientToken）则全部吊销。
     */
    public function changePassword(AdminUserModel $user, string $password, ?int $keepTokenId = null): void
    {
        $user->update(['password' => $password]);

        $user->tokens()
            ->when($keepTokenId, fn ($query) => $query->where('id', '!=', $keepTokenId))
            ->delete();
    }

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
            $this->writeLog($username, null, false, self::LOG_REASON_LOCKED, $ip, $userAgent);

            throw new AccountLockedException;
        }

        $user = AdminUserModel::query()->where('username', $username)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->writeLog($username, $user?->id, false, self::LOG_REASON_INVALID, $ip, $userAgent);

            throw new AuthenticationException('用户名或密码错误。');
        }

        if ((int) $user->status !== 1) {
            $this->writeLog($username, $user->id, false, self::LOG_REASON_DISABLED, $ip, $userAgent);

            throw new AccountDisabledException;
        }

        $this->writeLog($username, $user->id, true, null, $ip, $userAgent);

        return ['user' => $user, ...$this->issueTokens($user)];
    }

    /**
     * 签发一对令牌：access（短时）+ refresh（长时）。
     *
     * 两者共用同一个 session_id 标记为「同一次登录会话」，登出时凭 refresh
     * token 定位该会话即可连带吊销还没过期的 access token。
     * refresh 轮换时传入原 session_id，保证整条会话仍归为一组。
     *
     * @return array{accessToken: string, refreshToken: string}
     */
    public function issueTokens(AdminUserModel $user, ?string $sessionId = null): array
    {
        $sessionId ??= (string) Str::uuid();

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

        foreach ([$access, $refresh] as $issued) {
            $issued->accessToken->update(['session_id' => $sessionId]);
        }

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
            throw new AuthenticationException('刷新令牌无效或已过期。');
        }

        $user = $token->tokenable;

        if (! $user instanceof AdminUserModel) {
            throw new AuthenticationException('刷新令牌无效或已过期。');
        }

        // 账号被停用后不得再换新令牌（登录与 access 校验都认 status，这里不能破例）。
        // 顺带清掉该用户残留的令牌，避免被反复刷新探测。
        if ((int) $user->status !== 1) {
            $this->revokeAllTokens($user);

            throw new AuthenticationException('刷新令牌无效或已过期。');
        }

        $sessionId = $token->session_id;
        $token->delete();

        return $this->issueTokens($user, $sessionId);
    }

    /**
     * 结束一次登录会话：凭 refresh token 定位 session_id，连同 access token 一起吊销。
     *
     * 只删 refresh 会留下仍可用的 access token，直到其自然过期（最长 ACCESS_TTL_MINUTES 分钟）。
     */
    public function revokeSession(string $refreshToken): void
    {
        $token = PersonalAccessTokenModel::findToken($refreshToken);

        if (! $token) {
            return;
        }

        // 老数据（迁移前签发）没有 session_id，退化为只吊销自身
        if ($token->session_id) {
            PersonalAccessTokenModel::query()
                ->where('session_id', $token->session_id)
                ->delete();

            return;
        }

        $token->delete();
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
     *
     * 锁定期间被拦下的尝试（reason=locked）只进审计日志，**不计入**阈值：
     * 否则攻击者只要持续探测就能把 15 分钟窗口不断右移，把账号永久锁死（DoS）。
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
            ->where(fn ($query) => $query->whereNull('reason')->orWhere('reason', '!=', self::LOG_REASON_LOCKED))
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
