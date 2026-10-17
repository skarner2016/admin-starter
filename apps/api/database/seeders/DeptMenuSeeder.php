<?php

namespace Database\Seeders;

use App\Models\MenuModel;
use App\Models\RoleModel;
use App\Services\RoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 部门模块的菜单行与三个按钮权限码。
 *
 * 幂等：按 name 定位并更新，只对 super/admin **追加**授权。
 * 注意不要改用「重跑 MenuSeeder」来加部门 —— 它开头会 delete 掉全部菜单与 admin_role_menu，
 * 等于回滚管理员在后台维护的菜单与角色授权。
 */
class DeptMenuSeeder extends Seeder
{
    private const PAGE = [
        'name' => 'SystemDept',
        'path' => 'dept',
        'component' => '/system/dept/index',
        'icon' => 'carbon:container-services',
        'sort' => 4,
        'title' => 'system.dept.title',
    ];

    /**
     * 权限码由 type=button 行的 auth_code 派生（见 RoleService::syncMenusAndPermissions）。
     */
    private const BUTTONS = [
        ['name' => 'SystemDeptCreate', 'auth_code' => 'System:Dept:Create', 'title' => 'common.create'],
        ['name' => 'SystemDeptEdit', 'auth_code' => 'System:Dept:Edit', 'title' => 'common.edit'],
        ['name' => 'SystemDeptDelete', 'auth_code' => 'System:Dept:Delete', 'title' => 'common.delete'],
    ];

    public function run(): void
    {
        $system = MenuModel::query()->where('name', 'System')->first();

        if (! $system) {
            throw new RuntimeException('未找到 System 菜单目录，请先执行 MenuSeeder。');
        }

        $page = $this->upsertMenu([
            'name' => self::PAGE['name'],
            'path' => self::PAGE['path'],
            'component' => self::PAGE['component'],
            'type' => MenuModel::TYPE_MENU,
            'icon' => self::PAGE['icon'],
            'sort' => self::PAGE['sort'],
            'meta' => ['title' => self::PAGE['title']],
        ], $system->id);

        $buttons = [];

        foreach (self::BUTTONS as $index => $button) {
            $buttons[] = $this->upsertMenu([
                'name' => $button['name'],
                // 按钮类节点没有路由地址
                'path' => null,
                'component' => null,
                'type' => MenuModel::TYPE_BUTTON,
                'auth_code' => $button['auth_code'],
                'sort' => $index + 1,
                'meta' => ['title' => $button['title']],
            ], $page->id);
        }

        $menus = [$page, ...$buttons];

        // super 与 admin 原本就能看到整个系统管理；user 不给，保持既有语义
        $this->grant('super', $menus);
        $this->grant('admin', $menus);
    }

    private function upsertMenu(array $attributes, int $pid): MenuModel
    {
        return MenuModel::query()->updateOrCreate(
            ['name' => $attributes['name']],
            [...$attributes, 'pid' => $pid, 'status' => 1],
        );
    }

    /**
     * 追加授权：带上角色原有的 menu id，避免 syncMenusAndPermissions 清掉既有授权。
     *
     * @param  array<int, MenuModel>  $menus
     */
    private function grant(string $roleName, array $menus): void
    {
        $role = RoleModel::findByName($roleName, 'web');

        if (! $role) {
            return;
        }

        $existing = DB::table('admin_role_menu')
            ->where('role_id', $role->id)
            ->pluck('menu_id')
            ->all();

        $ids = array_values(array_unique([
            ...array_map('intval', $existing),
            ...array_map(fn (MenuModel $menu) => $menu->id, $menus),
        ]));

        app(RoleService::class)->syncMenusAndPermissions($role, $ids);
    }
}
