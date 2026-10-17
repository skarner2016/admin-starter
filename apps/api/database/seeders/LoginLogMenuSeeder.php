<?php

namespace Database\Seeders;

use App\Models\MenuModel;
use App\Models\RoleModel;
use App\Services\RoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 登录日志菜单。
 *
 * 登录日志含 IP / UA，属于敏感数据，所以它的**读**接口也挂权限码
 * （本模块唯一的「读需要权限码」的例子）：System:LoginLog:View。
 * 该权限码同样由 type=button 行派生，与其它按钮码走同一条链路。
 *
 * 幂等：按 name 定位并更新，只对 super/admin 追加授权。
 */
class LoginLogMenuSeeder extends Seeder
{
    private const PAGE = [
        'name' => 'SystemLoginLog',
        'path' => 'login-log',
        'component' => '/system/login-log/index',
        'icon' => 'carbon:document-tasks',
        'sort' => 5,
        'title' => 'system.loginLog.title',
    ];

    private const VIEW_BUTTON = [
        'name' => 'SystemLoginLogView',
        'auth_code' => 'System:LoginLog:View',
        'title' => 'system.loginLog.view',
    ];

    public function run(): void
    {
        $system = MenuModel::query()->where('name', 'System')->first();

        if (! $system) {
            throw new RuntimeException('未找到 System 菜单目录，请先执行 MenuSeeder。');
        }

        $page = MenuModel::query()->updateOrCreate(
            ['name' => self::PAGE['name']],
            [
                'pid' => $system->id,
                'path' => self::PAGE['path'],
                'component' => self::PAGE['component'],
                'type' => MenuModel::TYPE_MENU,
                'icon' => self::PAGE['icon'],
                'sort' => self::PAGE['sort'],
                'status' => 1,
                'meta' => ['title' => self::PAGE['title']],
            ],
        );

        $view = MenuModel::query()->updateOrCreate(
            ['name' => self::VIEW_BUTTON['name']],
            [
                'pid' => $page->id,
                'path' => null,
                'component' => null,
                'type' => MenuModel::TYPE_BUTTON,
                'auth_code' => self::VIEW_BUTTON['auth_code'],
                'sort' => 1,
                'status' => 1,
                'meta' => ['title' => self::VIEW_BUTTON['title']],
            ],
        );

        $this->grant('super', [$page, $view]);
        $this->grant('admin', [$page, $view]);
    }

    /**
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
