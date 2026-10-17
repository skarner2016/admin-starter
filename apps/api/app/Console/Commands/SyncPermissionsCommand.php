<?php

namespace App\Console\Commands;

use App\Models\MenuModel;
use App\Models\RoleModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'rbac:sync-permissions {--prune : 同时删除菜单表中已不存在的权限码}';

    protected $description = '以菜单表（type=button 的 auth_code）为准重建权限表，并重新派生各角色的权限';

    public function handle(): int
    {
        $codes = MenuModel::query()
            ->where('type', MenuModel::TYPE_BUTTON)
            ->whereNotNull('auth_code')
            ->pluck('auth_code')
            ->unique()
            ->values()
            ->all();

        foreach ($codes as $code) {
            Permission::findOrCreate($code, 'web');
        }

        $pruned = 0;

        if ($this->option('prune')) {
            $pruned = Permission::query()
                ->where('guard_name', 'web')
                ->whereNotIn('name', $codes)
                ->delete();
        }

        $roles = RoleModel::query()->get();

        foreach ($roles as $role) {
            $role->syncPermissions($this->codesForRole($role->id));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info(sprintf(
            '同步完成：按钮权限码 %d 个，重算角色 %d 个，清理权限 %d 个。',
            count($codes),
            $roles->count(),
            $pruned,
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function codesForRole(int $roleId): array
    {
        $menuIds = DB::table('admin_role_menu')->where('role_id', $roleId)->pluck('menu_id')->all();

        return MenuModel::query()
            ->whereIn('id', $menuIds)
            ->where('type', MenuModel::TYPE_BUTTON)
            ->whereNotNull('auth_code')
            ->pluck('auth_code')
            ->unique()
            ->values()
            ->all();
    }
}
