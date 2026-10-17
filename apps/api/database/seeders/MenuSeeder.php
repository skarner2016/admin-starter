<?php

namespace Database\Seeders;

use App\Models\MenuModel;
use App\Models\RoleModel;
use App\Services\RoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('admin_role_menu')->delete();
        MenuModel::query()->delete();

        // component 必须是 views/ 下真实存在的文件，否则前端会回退到 not-found 页
        $dashboard = $this->create([
            'name' => 'Dashboard',
            'path' => '/dashboard',
            'type' => MenuModel::TYPE_CATALOG,
            'icon' => 'lucide:layout-dashboard',
            'sort' => -1,
            'meta' => ['title' => 'page.dashboard.title'],
        ]);

        $workspace = $this->create([
            'name' => 'Workspace',
            'path' => 'workspace',
            'component' => '/dashboard/workspace/index',
            'type' => MenuModel::TYPE_MENU,
            'icon' => 'carbon:workspace',
            'sort' => 1,
            'meta' => ['title' => 'page.dashboard.workspace'],
        ], $dashboard->id);

        $analytics = $this->create([
            'name' => 'Analytics',
            'path' => 'analytics',
            'component' => '/dashboard/analytics/index',
            'type' => MenuModel::TYPE_MENU,
            'icon' => 'lucide:area-chart',
            'sort' => 2,
            'meta' => ['title' => 'page.dashboard.analytics', 'affixTab' => true],
        ], $dashboard->id);

        $demos = $this->create([
            'name' => 'Demos',
            'path' => '/demos',
            'type' => MenuModel::TYPE_CATALOG,
            'icon' => 'ic:baseline-view-in-ar',
            'sort' => 1000,
            'meta' => ['title' => 'demos.title'],
        ]);

        $element = $this->create([
            'name' => 'DemosElement',
            'path' => 'element',
            'component' => '/demos/element/index',
            'type' => MenuModel::TYPE_MENU,
            'sort' => 1,
            'meta' => ['title' => 'demos.elementPlus'],
        ], $demos->id);

        $form = $this->create([
            'name' => 'DemosForm',
            'path' => 'form',
            'component' => '/demos/form/basic',
            'type' => MenuModel::TYPE_MENU,
            'sort' => 2,
            'meta' => ['title' => 'demos.form'],
        ], $demos->id);

        // ===== 系统管理：页面 + 按钮权限码 =====
        $system = $this->create([
            'name' => 'System',
            'path' => '/system',
            'type' => MenuModel::TYPE_CATALOG,
            'icon' => 'carbon:settings',
            'sort' => 2000,
            'meta' => ['title' => 'system.title'],
        ]);

        $systemPages = [
            [
                'name' => 'SystemUser',
                'path' => 'user',
                'component' => '/system/user/index',
                'icon' => 'carbon:user-multiple',
                'title' => 'system.user.title',
                'code' => 'System:User',
            ],
            [
                'name' => 'SystemRole',
                'path' => 'role',
                'component' => '/system/role/index',
                'icon' => 'carbon:user-role',
                'title' => 'system.role.title',
                'code' => 'System:Role',
            ],
            [
                'name' => 'SystemMenu',
                'path' => 'menu',
                'component' => '/system/menu/index',
                'icon' => 'carbon:menu',
                'title' => 'system.menu.title',
                'code' => 'System:Menu',
            ],
        ];

        $systemMenus = [];

        foreach ($systemPages as $index => $page) {
            $menu = $this->create([
                'name' => $page['name'],
                'path' => $page['path'],
                'component' => $page['component'],
                'type' => MenuModel::TYPE_MENU,
                'icon' => $page['icon'],
                'sort' => $index + 1,
                'meta' => ['title' => $page['title']],
            ], $system->id);

            $buttons = [];

            foreach (['Create' => 'common.create', 'Edit' => 'common.edit', 'Delete' => 'common.delete'] as $action => $title) {
                $buttons[] = $this->create([
                    'name' => $page['name'].$action,
                    'type' => MenuModel::TYPE_BUTTON,
                    'auth_code' => $page['code'].':'.$action,
                    'sort' => count($buttons) + 1,
                    'meta' => ['title' => $title],
                ], $menu->id);
            }

            $systemMenus = [...$systemMenus, $menu, ...$buttons];
        }

        // 顶栏「个人中心」按路由 name 跳转，accessMode=backend 后必须由后端下发才可路由
        $profile = $this->create([
            'name' => 'Profile',
            'path' => '/profile',
            'component' => '/_core/profile/index',
            'type' => MenuModel::TYPE_MENU,
            'icon' => 'lucide:user',
            'sort' => 9998,
            'meta' => ['title' => 'page.auth.profile', 'hideInMenu' => true],
        ]);

        $about = $this->create([
            'name' => 'VbenAbout',
            'path' => '/vben-admin/about',
            'component' => '/_core/about/index',
            'type' => MenuModel::TYPE_MENU,
            'icon' => 'lucide:copyright',
            'sort' => 9999,
            'meta' => ['title' => 'demos.vben.about'],
        ]);

        $dashboardTree = [$dashboard, $workspace, $analytics];
        $demosTree = [$demos, $element, $form];
        $personal = [$profile, $about];

        // 授权即写 admin_role_menu；spatie 权限由按钮行的 auth_code 派生
        $this->assign('super', [...$dashboardTree, ...$demosTree, $system, ...$systemMenus, ...$personal]);
        $this->assign('admin', [...$dashboardTree, ...$demosTree, $system, ...$systemMenus, ...$personal]);
        // user 看不到 Demos 与系统管理，用于验证按角色下发菜单
        $this->assign('user', [...$dashboardTree, ...$personal]);
    }

    private function create(array $attributes, int $pid = 0): MenuModel
    {
        return MenuModel::query()->create([
            ...$attributes,
            'pid' => $pid,
            'status' => 1,
        ]);
    }

    /**
     * @param  array<int, MenuModel>  $menus
     */
    private function assign(string $roleName, array $menus): void
    {
        $role = RoleModel::findByName($roleName, 'web');

        app(RoleService::class)->syncMenusAndPermissions(
            $role,
            array_map(fn (MenuModel $menu) => $menu->id, $menus),
        );
    }
}
