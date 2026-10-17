<?php

namespace App\Services;

use App\Models\AdminUserModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdminUserManageService
{
    public function __construct(
        private readonly AdminUserService $auth,
        private readonly DeptService $depts,
    ) {}

    public function paginate(array $filters, int $page, int $pageSize): LengthAwarePaginator
    {
        return AdminUserModel::query()
            ->with(['dept', 'roles'])
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
            // 按部门筛选时含其所有子孙部门，先取子树 id 集合再 whereIn
            ->when(
                $this->deptId($filters['deptId'] ?? null),
                fn ($query, $deptId) => $query->whereIn('dept_id', $this->depts->descendantIds($deptId)),
            )
            ->orderByDesc('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    public function create(array $data): AdminUserModel
    {
        return DB::transaction(function () use ($data) {
            if (in_array(AdminUserService::SUPER_ROLE, $data['roles'] ?? [], true)
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
                'dept_id' => $this->deptId($data['deptId'] ?? null),
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
            $current = auth()->user();

            // 自锁保护：不允许停用自己（与「不能删除当前账号」对称）
            if ($current instanceof AdminUserModel
                && $current->id === $user->id
                && (int) ($data['status'] ?? 1) !== 1
            ) {
                throw new AuthorizationException('不能停用当前登录账号。');
            }

            // 自锁保护：super 不允许摘掉自己的 super 角色
            if ($current instanceof AdminUserModel
                && $current->id === $user->id
                && $this->auth->isSuper($user)
                && ! in_array(AdminUserService::SUPER_ROLE, $data['roles'] ?? [], true)
            ) {
                throw new AuthorizationException('不能移除自己的 super 角色。');
            }

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
                'dept_id' => $this->deptId($data['deptId'] ?? null),
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

    /**
     * 删除前的业务护栏。单条与批量共用，保证两条路径规则一致。
     * 返回 null 表示允许删除。
     */
    public function deleteFailureReason(AdminUserModel $user, AdminUserModel $operator): ?string
    {
        if ($operator->id === $user->id) {
            return '不能删除当前登录账号。';
        }

        if ($user->hasRole(AdminUserService::SUPER_ROLE)
            && $this->countUsersWithRole(AdminUserService::SUPER_ROLE) <= 1
        ) {
            return '不能删除最后一个 super 账号。';
        }

        return null;
    }

    /**
     * 批量删除：逐条应用与单条相同的护栏，返回每条的结果，
     * 避免「一条失败导致整批回滚」。
     *
     * @param  array<int, int|string>  $ids
     * @return array{deleted: array<int, string>, failed: array<int, array{id: string, message: string}>}
     */
    public function batchDelete(array $ids, AdminUserModel $operator): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $users = AdminUserModel::query()->whereIn('id', $ids)->get()->keyBy('id');

        $deleted = [];
        $failed = [];

        foreach ($ids as $id) {
            $user = $users->get($id);

            if (! $user) {
                $failed[] = ['id' => (string) $id, 'message' => '用户不存在。'];

                continue;
            }

            $reason = $this->deleteFailureReason($user, $operator);

            if ($reason !== null) {
                $failed[] = ['id' => (string) $id, 'message' => $reason];

                continue;
            }

            try {
                $this->delete($user);
                $deleted[] = (string) $id;
            } catch (Throwable $e) {
                $failed[] = ['id' => (string) $id, 'message' => $e->getMessage()];
            }
        }

        return ['deleted' => $deleted, 'failed' => $failed];
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

    /**
     * 空值 / 0 一律视为「未分配部门」，统一落 null。
     */
    private function deptId(mixed $value): ?int
    {
        if ($value === null || $value === '' || (int) $value === 0) {
            return null;
        }

        return (int) $value;
    }
}
