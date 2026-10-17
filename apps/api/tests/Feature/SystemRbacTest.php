<?php

namespace Tests\Feature;

use App\Models\AdminUserModel;
use App\Models\MenuModel;
use App\Models\RoleModel;
use App\Services\AdminUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SystemRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function actingAsUser(string $username): void
    {
        Sanctum::actingAs(
            AdminUserModel::query()->where('username', $username)->firstOrFail(),
            ['*'],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function flatten(array $nodes): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $result[] = $node;

            if (! empty($node['children'])) {
                $result = [...$result, ...$this->flatten($node['children'])];
            }
        }

        return $result;
    }

    // ===================== 菜单 =====================

    public function test_menu_list_returns_full_tree_with_all_types_and_string_ids(): void
    {
        $this->actingAsUser('vben');

        $tree = $this->getJson('/api/system/menu/list')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->json('data');

        $all = collect($this->flatten($tree))->keyBy('name');

        // 管理视图能看到按钮类节点与所有状态
        $this->assertTrue($all->has('SystemUserCreate'));

        $system = $all->get('System');
        $this->assertSame('0', $system['pid']);
        $this->assertIsString($system['id']);
        $this->assertSame('catalog', $system['type']);

        $systemUser = $all->get('SystemUser');
        $this->assertSame('menu', $systemUser['type']);
        $this->assertSame('/system/user/index', $systemUser['component']);

        $button = $all->get('SystemUserCreate');
        $this->assertSame('button', $button['type']);
        $this->assertSame('System:User:Create', $button['authCode']);
        // 按钮类没有路由地址
        $this->assertNull($button['path']);
    }

    public function test_name_exists_excludes_self(): void
    {
        $this->actingAsUser('vben');
        $menu = MenuModel::query()->where('name', 'SystemUser')->firstOrFail();

        $this->getJson('/api/system/menu/name-exists?name=SystemUser')
            ->assertOk()->assertJsonPath('data', true);

        $this->getJson("/api/system/menu/name-exists?name=SystemUser&id={$menu->id}")
            ->assertOk()->assertJsonPath('data', false);

        $this->getJson('/api/system/menu/name-exists?name=NotExists')
            ->assertOk()->assertJsonPath('data', false);
    }

    public function test_path_exists_ignores_self(): void
    {
        $this->actingAsUser('vben');
        $menu = MenuModel::query()->where('name', 'SystemUser')->firstOrFail();

        $this->getJson('/api/system/menu/path-exists?path=user')
            ->assertOk()->assertJsonPath('data', true);

        $this->getJson("/api/system/menu/path-exists?path=user&id={$menu->id}")
            ->assertOk()->assertJsonPath('data', false);
    }

    public function test_creating_button_menu_creates_permission(): void
    {
        $this->actingAsUser('vben');
        $parent = MenuModel::query()->where('name', 'SystemUser')->firstOrFail();

        $this->postJson('/api/system/menu', [
            'name' => 'SystemUserExport',
            'type' => 'button',
            'authCode' => 'System:User:Export',
            'pid' => (string) $parent->id,
            'status' => 1,
            'meta' => ['title' => 'common.export'],
        ])->assertOk()->assertJsonPath('code', 0);

        $this->assertDatabaseHas('admin_permissions', [
            'name' => 'System:User:Export',
            'guard_name' => 'web',
        ]);
    }

    public function test_updating_button_auth_code_renames_permission(): void
    {
        $this->actingAsUser('vben');
        $button = MenuModel::query()->where('name', 'SystemUserCreate')->firstOrFail();

        $this->putJson("/api/system/menu/{$button->id}", [
            'name' => 'SystemUserCreate',
            'type' => 'button',
            'authCode' => 'System:User:Add',
            'pid' => (string) $button->pid,
            'status' => 1,
        ])->assertOk()->assertJsonPath('code', 0);

        $this->assertDatabaseHas('admin_permissions', ['name' => 'System:User:Add']);
        $this->assertDatabaseMissing('admin_permissions', ['name' => 'System:User:Create']);
    }

    public function test_deleting_button_menu_removes_permission_and_role_link(): void
    {
        $this->actingAsUser('vben');
        $button = MenuModel::query()->where('name', 'SystemRoleDelete')->firstOrFail();

        $this->deleteJson("/api/system/menu/{$button->id}")->assertOk();

        $this->assertDatabaseMissing('admin_permissions', ['name' => 'System:Role:Delete']);
        $this->assertDatabaseMissing('admin_role_menu', ['menu_id' => $button->id]);
    }

    public function test_delete_menu_with_children_is_rejected(): void
    {
        $this->actingAsUser('vben');
        $parent = MenuModel::query()->where('name', 'SystemUser')->firstOrFail();

        $this->deleteJson("/api/system/menu/{$parent->id}")
            ->assertOk()
            ->assertJsonPath('code', -1);

        $this->assertDatabaseHas('admin_menus', ['id' => $parent->id]);
    }

    // ===================== 角色 =====================

    public function test_role_list_returns_pagination_shape_with_menu_ids(): void
    {
        $this->actingAsUser('vben');

        $response = $this->getJson('/api/system/role/list?page=1&pageSize=10')
            ->assertOk()
            ->assertJsonPath('data.total', 3);

        $items = $response->json('data.items');
        $this->assertCount(3, $items);

        $super = collect($items)->firstWhere('name', 'super');
        $this->assertIsString($super['id']);
        $this->assertSame(1, $super['status']);
        $this->assertSame('超级管理员，拥有全部权限', $super['remark']);
        // permissions 是 menu id 数组，且以字符串返回
        $this->assertNotEmpty($super['permissions']);
        $this->assertIsString($super['permissions'][0]);
    }

    public function test_role_create_syncs_menu_assignment_and_derived_codes(): void
    {
        $this->actingAsUser('vben');

        $page = MenuModel::query()->where('name', 'SystemUser')->firstOrFail();
        $button = MenuModel::query()->where('name', 'SystemUserCreate')->firstOrFail();

        $created = $this->postJson('/api/system/role', [
            'name' => 'auditor',
            'status' => 1,
            'remark' => '审计员',
            'permissions' => [(string) $page->id, (string) $button->id],
        ])->assertOk()->assertJsonPath('code', 0)->json('data');

        $this->assertSame('auditor', $created['name']);

        $role = RoleModel::findByName('auditor', 'web');

        // ① admin_role_menu 落地
        $this->assertSame(
            [$page->id, $button->id],
            DB::table('admin_role_menu')
                ->where('role_id', $role->id)
                ->orderBy('menu_id')
                ->pluck('menu_id')
                ->all(),
        );

        // ② spatie 权限由按钮行的 auth_code 派生
        $this->assertSame(['System:User:Create'], $role->permissions->pluck('name')->all());
    }

    public function test_role_update_recomputes_derived_codes(): void
    {
        $this->actingAsUser('vben');
        $role = RoleModel::findByName('user', 'web');
        $this->assertCount(0, $role->permissions);

        $button = MenuModel::query()->where('name', 'SystemRoleDelete')->firstOrFail();

        $this->putJson("/api/system/role/{$role->id}", [
            'name' => 'user',
            'status' => 1,
            'permissions' => [(string) $button->id],
        ])->assertOk()->assertJsonPath('code', 0);

        $this->assertSame(
            ['System:Role:Delete'],
            $role->fresh()->permissions->pluck('name')->all(),
        );
    }

    public function test_role_delete_blocked_when_users_bound(): void
    {
        $this->actingAsUser('vben');
        $role = RoleModel::findByName('admin', 'web');

        $this->deleteJson("/api/system/role/{$role->id}")
            ->assertOk()
            ->assertJsonPath('code', -1);

        $this->assertDatabaseHas('admin_roles', ['id' => $role->id]);
    }

    public function test_super_role_cannot_be_deleted(): void
    {
        $this->actingAsUser('vben');
        $role = RoleModel::findByName('super', 'web');

        $this->deleteJson("/api/system/role/{$role->id}")
            ->assertOk()
            ->assertJsonPath('code', -1);

        $this->assertDatabaseHas('admin_roles', ['id' => $role->id]);
    }

    // ===================== 用户 =====================

    public function test_user_list_returns_pagination_shape(): void
    {
        $this->actingAsUser('vben');

        $response = $this->getJson('/api/system/user/list?page=1&pageSize=10')
            ->assertOk()
            ->assertJsonPath('data.total', 3);

        $items = $response->json('data.items');
        $vben = collect($items)->firstWhere('username', 'vben');

        $this->assertIsString($vben['id']);
        $this->assertSame('Vben', $vben['realName']);
        $this->assertSame(['super'], $vben['roles']);
        $this->assertSame(1, $vben['status']);
    }

    public function test_user_create_update_and_delete(): void
    {
        $this->actingAsUser('vben');

        $created = $this->postJson('/api/system/user', [
            'username' => 'newbie',
            'realName' => 'Newbie',
            // UserRequest 要求密码含大小写与数字
            'password' => 'Secret123',
            'status' => 1,
            'roles' => ['admin'],
        ])->assertOk()->assertJsonPath('code', 0)->json('data');

        $this->assertSame(['admin'], $created['roles']);
        $this->assertDatabaseHas('admin_users', ['username' => 'newbie', 'real_name' => 'Newbie']);

        $id = $created['id'];

        $updated = $this->putJson("/api/system/user/{$id}", [
            'username' => 'newbie',
            'realName' => 'Newbie Renamed',
            'status' => 1,
            'roles' => ['user'],
        ])->assertOk()->json('data');

        $this->assertSame(['user'], $updated['roles']);
        $this->assertSame('Newbie Renamed', $updated['realName']);

        $this->deleteJson("/api/system/user/{$id}")->assertOk();
        $this->assertDatabaseMissing('admin_users', ['id' => $id]);
    }

    public function test_cannot_delete_current_user(): void
    {
        $this->actingAsUser('vben');
        $vben = AdminUserModel::query()->where('username', 'vben')->firstOrFail();

        $this->deleteJson("/api/system/user/{$vben->id}")
            ->assertOk()
            ->assertJsonPath('code', -1);

        $this->assertDatabaseHas('admin_users', ['id' => $vben->id]);
    }

    public function test_cannot_delete_last_super_user(): void
    {
        $this->actingAsUser('admin');
        $vben = AdminUserModel::query()->where('username', 'vben')->firstOrFail();

        $this->deleteJson("/api/system/user/{$vben->id}")
            ->assertOk()
            ->assertJsonPath('code', -1);

        $this->assertDatabaseHas('admin_users', ['id' => $vben->id]);
    }

    public function test_reset_password_revokes_existing_tokens(): void
    {
        $this->actingAsUser('vben');
        $jack = AdminUserModel::query()->where('username', 'jack')->firstOrFail();

        app(AdminUserService::class)->issueTokens($jack);
        $this->assertSame(2, $jack->tokens()->count());

        $this->putJson("/api/system/user/{$jack->id}/password", ['password' => 'brand-new-pass'])
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertSame(0, $jack->tokens()->count());
        $this->assertTrue(Hash::check('brand-new-pass', $jack->fresh()->password));
    }

    public function test_disabling_user_revokes_tokens(): void
    {
        $this->actingAsUser('vben');
        $jack = AdminUserModel::query()->where('username', 'jack')->firstOrFail();

        app(AdminUserService::class)->issueTokens($jack);

        $this->putJson("/api/system/user/{$jack->id}", [
            'username' => 'jack',
            'realName' => 'Jack',
            'status' => 0,
            'roles' => ['user'],
        ])->assertOk();

        $this->assertSame(0, $jack->tokens()->count());
    }

    // ===================== 权限校验 =====================

    public function test_reads_require_only_authentication_but_writes_require_permission(): void
    {
        // user 角色没有任何按钮权限码
        $this->actingAsUser('jack');

        $this->getJson('/api/system/user/list')->assertOk()->assertJsonPath('code', 0);

        $this->postJson('/api/system/user', [
            'username' => 'sneaky',
            'realName' => 'Sneaky',
            'password' => 'secret123',
            'status' => 1,
        ])->assertStatus(403);

        $this->assertDatabaseMissing('admin_users', ['username' => 'sneaky']);
    }

    public function test_super_bypasses_permission_checks(): void
    {
        $this->actingAsUser('vben');

        $this->postJson('/api/system/role', [
            'name' => 'temp-role',
            'status' => 1,
        ])->assertOk()->assertJsonPath('code', 0);
    }

    // ===================== 一致性兜底命令 =====================

    public function test_sync_permissions_command_repairs_drift(): void
    {
        Permission::query()->where('name', 'System:Menu:Delete')->delete();
        $this->assertDatabaseMissing('admin_permissions', ['name' => 'System:Menu:Delete']);

        $this->artisan('rbac:sync-permissions')->assertSuccessful();

        $this->assertDatabaseHas('admin_permissions', ['name' => 'System:Menu:Delete']);

        // 角色权限被重新派生回来
        $super = RoleModel::findByName('super', 'web');
        $this->assertContains('System:Menu:Delete', $super->permissions->pluck('name')->all());
    }
}
