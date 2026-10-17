<?php

namespace App\Services;

use App\Models\AdminUserModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminUserManageService
{
    public function __construct(private readonly AdminUserService $auth) {}

    public function paginate(array $filters, int $page, int $pageSize): LengthAwarePaginator
    {
        return AdminUserModel::query()
            ->with('roles')
            ->when(
                $filters['username'] ?? null,
                fn ($query, $username) => $query->where('username', 'like', "%{$username}%"),
            )
            ->when(
                $filters['realName'] ?? null,
                fn ($query, $realName) => $query->where('real_name', 'like', "%{$realName}%"),
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn ($query) => $query->where('status', (int) $filters['status']),
            )
            ->orderByDesc('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    public function create(array $data): AdminUserModel
    {
        return DB::transaction(function () use ($data) {
            if (in_array(self::SUPER_ROLE, $data['roles'] ?? [], true)
                && ! $this->auth->isSuper(auth()->user())
            ) {
                throw new AuthorizationException('只有 super 才能分配 super 角色。');
            }

            $user = AdminUserModel::query()->create([
                'username' => $data['username'],
                'real_name' => $data['realName'],
                'avatar' => $data['avatar'] ?? null,
                'password' => $data['password'],
                'description' => $data['description'] ?? null,
                'status' => (int) ($data['status'] ?? 1),
            ]);

            $user->syncRoles($data['roles'] ?? []);

            return $user;
        });
    }

    public function update(AdminUserModel $user, array $data): AdminUserModel
    {
        return DB::transaction(function () use ($user, $data) {
            $wasEnabled = (int) $user->status === 1;

            // super 保护：非 super 不可修改 super 用户（含停用、改角色）
            if ($user->hasRole(AdminUserService::SUPER_ROLE)
                && ! $this->auth->isSuper(auth()->user())
            ) {
                throw new AuthorizationException('只有 super 才能修改 super 账号。');
            }

            // 非 super 不可把账号提升为 super
            if (in_array(AdminUserService::SUPER_ROLE, $data['roles'] ?? [], true)
                && ! $this->auth->isSuper(auth()->user())
            ) {
                throw new AuthorizationException('只有 super 才能分配 super 角色。');
            }

            $user->update([
                'username' => $data['username'],
                'real_name' => $data['realName'],
                'avatar' => $data['avatar'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => (int) ($data['status'] ?? 1),
            ]);

            $user->syncRoles($data['roles'] ?? []);

            // 账号被停用时立即踢下线（access + refresh 一起删，否则旧 access 还能用到过期）。
            // 改角色不需要吊销：/auth/codes 与 /menu/all 每次请求都按当前角色重新计算。
            if ($wasEnabled && (int) $user->status !== 1) {
                $this->auth->revokeAllTokens($user);
            }

            return $user;
        });
    }

    public function delete(AdminUserModel $user): void
    {
        if ($user->hasRole(AdminUserService::SUPER_ROLE)) {
            throw new AuthorizationException('super 账号不允许删除。');
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->syncRoles([]);
            $user->delete();
        });
    }

    public function resetPassword(AdminUserModel $user, string $password): void
    {
        DB::transaction(function () use ($user, $password) {
            $user->update(['password' => $password]);
            // 重置密码后旧会话必须立即失效
            $this->auth->revokeAllTokens($user);
        });
    }

    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        return AdminUserModel::query()
            ->where('username', $username)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->exists();
    }

    public function countUsersWithRole(string $roleName): int
    {
        return AdminUserModel::query()->role($roleName)->count();
    }
}
