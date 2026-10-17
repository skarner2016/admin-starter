<?php

namespace App\Services;

use App\Http\Resources\SystemMenuResource;
use App\Models\AdminUserModel;
use App\Models\MenuModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class MenuService
{
    /**
     * 构建当前用户可见的菜单路由树（/menu/all 出参）。
     * 只含 catalog/menu/embedded/link，过滤 status=0 与 button。
     */
    public function menusForUser(AdminUserModel $user): array
    {
        return $this->buildTree($this->visibleMenus($user), 0);
    }

    /**
     * 菜单管理用的完整树（/system/menu/list 出参）：含全部类型与状态。
     */
    public function tree(): array
    {
        return $this->buildAdminTree(MenuModel::query()->orderBy('sort')->orderBy('id')->get(), 0);
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        return MenuModel::query()
            ->where('name', $name)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->exists();
    }

    public function pathExists(string $path, ?int $excludeId = null): bool
    {
        return MenuModel::query()
            ->where('path', $path)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->exists();
    }

    public function create(array $data): MenuModel
    {
        return DB::transaction(function () use ($data) {
            $menu = MenuModel::query()->create($data);
            $this->syncPermission($menu);

            return $menu;
        });
    }

    public function update(MenuModel $menu, array $data): MenuModel
    {
        return DB::transaction(function () use ($menu, $data) {
            $previousCode = $menu->type === MenuModel::TYPE_BUTTON ? $menu->auth_code : null;
            $menu->update($data);
            $this->syncPermission($menu, $previousCode);

            return $menu;
        });
    }

    public function delete(MenuModel $menu): void
    {
        DB::transaction(function () use ($menu) {
            DB::table('admin_role_menu')->where('menu_id', $menu->id)->delete();
            $this->removePermission($menu->auth_code);
            $menu->delete();
        });
    }

    public function hasChildren(MenuModel $menu): bool
    {
        return MenuModel::query()->where('pid', $menu->id)->exists();
    }

    /**
     * @return Collection<int, MenuModel>
     */
    private function visibleMenus(AdminUserModel $user): Collection
    {
        $query = MenuModel::query()
            ->where('status', 1)
            ->where('type', '!=', MenuModel::TYPE_BUTTON)
            ->orderBy('sort');

        if (! $user->hasRole(AdminUserService::SUPER_ROLE)) {
            $roleIds = $user->roles()->pluck('id')->all();

            $menuIds = DB::table('admin_role_menu')
                ->whereIn('role_id', $roleIds)
                ->pluck('menu_id')
                ->all();

            $query->whereIn('id', $this->withAncestors($menuIds));
        }

        return $query->get();
    }

    /**
     * 补齐祖先节点：只授权子菜单时，父级目录缺失会导致整棵子树无法挂载。
     *
     * @param  array<int, int|string>  $menuIds
     * @return array<int, int|string>
     */
    private function withAncestors(array $menuIds): array
    {
        if ($menuIds === []) {
            return [];
        }

        $parents = MenuModel::query()->pluck('pid', 'id')->all();
        $result = $menuIds;

        foreach ($menuIds as $id) {
            $pid = $parents[$id] ?? 0;

            while ($pid && ! in_array($pid, $result, true)) {
                $result[] = $pid;
                $pid = $parents[$pid] ?? 0;
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, MenuModel>  $menus
     */
    private function buildTree(Collection $menus, int $pid): array
    {
        $tree = [];

        foreach ($menus as $menu) {
            if ($menu->pid !== $pid) {
                continue;
            }

            $node = $this->toRoute($menu);
            $children = $this->buildTree($menus, $menu->id);

            if ($children !== []) {
                $node['children'] = $children;
            }

            $tree[] = $node;
        }

        return $tree;
    }

    private function toRoute(MenuModel $menu): array
    {
        $meta = $menu->meta ?? [];
        $meta['order'] = $menu->sort;

        if ($menu->icon) {
            $meta['icon'] = $menu->icon;
        }

        $route = [
            'name' => $menu->name,
            'path' => $menu->path,
            'meta' => $meta,
        ];

        if ($menu->component) {
            $route['component'] = $menu->component;
        }

        if ($menu->redirect) {
            $route['redirect'] = $menu->redirect;
        }

        return $route;
    }

    /**
     * @param  Collection<int, MenuModel>  $menus
     */
    private function buildAdminTree(Collection $menus, int $pid): array
    {
        $tree = [];

        foreach ($menus as $menu) {
            if ($menu->pid !== $pid) {
                continue;
            }

            $node = (new SystemMenuResource($menu))->toArray(request());
            $children = $this->buildAdminTree($menus, $menu->id);

            if ($children !== []) {
                $node['children'] = $children;
            }

            $tree[] = $node;
        }

        return $tree;
    }

    /**
     * 保持 spatie 权限与菜单表 `type=button` 行的 auth_code 一致。
     */
    private function syncPermission(MenuModel $menu, ?string $previousCode = null): void
    {
        if ($menu->type !== MenuModel::TYPE_BUTTON || ! $menu->auth_code) {
            $this->removePermission($previousCode);

            return;
        }

        if ($previousCode && $previousCode !== $menu->auth_code) {
            // 重命名保留 permission id，角色已有授权不受影响
            $permission = $this->findPermission($previousCode);

            if ($permission) {
                $permission->update(['name' => $menu->auth_code]);
                $this->forgetPermissionCache();

                return;
            }
        }

        Permission::findOrCreate($menu->auth_code, 'web');
        $this->forgetPermissionCache();
    }

    private function removePermission(?string $code): void
    {
        if (! $code) {
            return;
        }

        $this->findPermission($code)?->delete();
        $this->forgetPermissionCache();
    }

    private function findPermission(string $name): ?Permission
    {
        return Permission::query()
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->first();
    }

    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
