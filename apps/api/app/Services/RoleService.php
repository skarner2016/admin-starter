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
