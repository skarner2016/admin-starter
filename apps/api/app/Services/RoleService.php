<?php

namespace App\Services;

use App\Models\MenuModel;
use App\Models\RoleModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class RoleService
{
    public function paginate(array $filters, int $page, int $pageSize): LengthAwarePaginator
    {
        $roles = RoleModel::query()
            ->when(
                $filters['name'] ?? null,
                fn ($query, $name) => $query->where('name', 'like', "%{$name}%"),
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn ($query) => $query->where('status', (int) $filters['status']),
            )
            ->orderBy('id')
            ->paginate($pageSize, ['*'], 'page', $page);

        $this->attachMenuIds($roles->getCollection());

        return $roles;
    }

    public function create(array $data): RoleModel
    {
        return DB::transaction(function () use ($data) {
            $role = RoleModel::query()->create([
                'name' => $data['name'],
                'guard_name' => 'web',
                'remark' => $data['remark'] ?? null,
                'status' => (int) ($data['status'] ?? 1),
            ]);

            $this->syncMenusAndPermissions($role, $data['permissions'] ?? []);

            return $role;
        });
    }

    public function update(RoleModel $role, array $data): RoleModel
    {
        if ($role->name === AdminUserService::SUPER_ROLE
            && ! auth()->user()->hasRole(AdminUserService::SUPER_ROLE)
        ) {
            throw new AuthorizationException('只有 super 才能修改 super 角色。');
        }

        // super 角色停用会让唯一拥有全量权限的角色失效，等价于自锁，直接禁止
        if ($role->name === AdminUserService::SUPER_ROLE && (int) ($data['status'] ?? 1) !== 1) {
            throw new AuthorizationException('super 角色不允许停用。');
        }

        return DB::transaction(function () use ($role, $data) {
            $role->update([
                'name' => $data['name'],
                'remark' => $data['remark'] ?? null,
                'status' => (int) ($data['status'] ?? 1),
            ]);

            $this->syncMenusAndPermissions($role, $data['permissions'] ?? []);

            return $role;
        });
    }

    public function delete(RoleModel $role): void
    {
        if ($role->name === AdminUserService::SUPER_ROLE) {
            throw new AuthorizationException('super 角色不允许删除。');
        }

        DB::transaction(function () use ($role) {
            DB::table('admin_role_menu')->where('role_id', $role->id)->delete();
            $role->syncPermissions([]);
            $role->delete();
        });
    }

    /**
     * 删除前的业务护栏。单条与批量共用，保证两条路径规则一致。
     */
    public function deleteFailureReason(RoleModel $role): ?string
    {
        if ($role->name === AdminUserService::SUPER_ROLE) {
            return 'super 角色不允许删除。';
        }

        if ($this->userCount($role) > 0) {
            return '该角色下仍有用户，无法删除。';
        }

        return null;
    }

    /**
     * 批量删除：逐条应用与单条相同的护栏，返回每条的结果。
     *
     * @param  array<int, int|string>  $ids
     * @return array{deleted: array<int, string>, failed: array<int, array{id: string, message: string}>}
     */
    public function batchDelete(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $roles = RoleModel::query()->whereIn('id', $ids)->get()->keyBy('id');

        $deleted = [];
        $failed = [];

        foreach ($ids as $id) {
            $role = $roles->get($id);

            if (! $role) {
                $failed[] = ['id' => (string) $id, 'message' => '角色不存在。'];

                continue;
            }

            $reason = $this->deleteFailureReason($role);

            if ($reason !== null) {
                $failed[] = ['id' => (string) $id, 'message' => $reason];

                continue;
            }

            $this->delete($role);
            $deleted[] = (string) $id;
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }

    public function userCount(RoleModel $role): int
    {
        return DB::table('admin_model_has_roles')->where('role_id', $role->id)->count();
    }

    public function menuIds(RoleModel $role): array
    {
        return DB::table('admin_role_menu')
            ->where('role_id', $role->id)
            ->pluck('menu_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * 授权桥接：前端提交的是 menu id 数组（菜单树勾选）。
     * ① `admin_role_menu` 存角色 ↔ 菜单，是唯一事实源；
     * ② spatie 权限由该集合里 `type=button` 行的 auth_code 派生同步，供 /auth/codes 使用。
     *
     * @param  array<int, int|string>  $menuIds
     */
    public function syncMenusAndPermissions(RoleModel $role, array $menuIds): void
    {
        $menuIds = collect($menuIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        DB::table('admin_role_menu')->where('role_id', $role->id)->delete();

        if ($menuIds !== []) {
            DB::table('admin_role_menu')->insert(array_map(
                fn (int $menuId) => ['role_id' => $role->id, 'menu_id' => $menuId],
                $menuIds,
            ));
        }

        $codes = MenuModel::query()
            ->whereIn('id', $menuIds)
            ->where('type', MenuModel::TYPE_BUTTON)
            ->whereNotNull('auth_code')
            ->pluck('auth_code')
            ->unique()
            ->values()
            ->all();

        foreach ($codes as $code) {
            Permission::findOrCreate($code, 'web');
        }

        $role->syncPermissions($codes);
    }

    /**
     * @param  Collection<int, RoleModel>  $roles
     */
    private function attachMenuIds(Collection $roles): void
    {
        if ($roles->isEmpty()) {
            return;
        }

        $map = DB::table('admin_role_menu')
            ->whereIn('role_id', $roles->pluck('id'))
            ->get()
            ->groupBy('role_id');

        foreach ($roles as $role) {
            $role->setAttribute(
                'menu_ids',
                ($map[$role->id] ?? collect())
                    ->pluck('menu_id')
                    ->map(fn ($id) => (string) $id)
                    ->values()
                    ->all(),
            );
        }
    }
}
