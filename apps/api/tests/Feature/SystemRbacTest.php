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

    public function test_duplicate_auth_code_is_rejected(): void
    {
        $this->actingAsUser('vben');

        // 新建按钮：与既有 SystemUserCreate 共用同一权限码
        $this->postJson('/api/system/menu', [
            'name' => 'SystemUserCreateDup',
            'type' => 'button',
            'authCode' => 'System:User:Create',
            'pid' => '0',
            'status' => 1,
        ])->assertStatus(422);

        // 改名撞车：把 SystemUserCreate 的权限码改成 SystemRoleDelete 已占用的码
        $button = MenuModel::query()->where('name', 'SystemUserCreate')->firstOrFail();

        $this->putJson("/api/system/menu/{$button->id}", [
            'name' => 'SystemUserCreate',
            'type' => 'button',
            'authCode' => 'System:Role:Delete',
            'pid' => (string) $button->pid,
            'status' => 1,
        ])->assertStatus(422);

        // 权限仍在原处，未被误改
        $this->assertDatabaseHas('admin_permissions', ['name' => 'System:User:Create']);
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

    public function test_menu_sort_is_persisted_and_exposed_as_meta_order(): void
    {
        $this->actingAsUser('vben');

        $created = $this->postJson('/api/system/menu', [
            'name' => 'SortedMenu',
            'path' => '/sorted',
            'type' => 'menu',
            'status' => 1,
            'sort' => 7,
            'meta' => ['title' => 'sorted'],
        ])->assertOk()->assertJsonPath('code', 0)->json('data');

        // 排序落在 admin_menus.sort（权威来源），而不是只藏在 meta.order 里
        $this->assertSame(7, $created['sort']);
        $this->assertDatabaseHas('admin_menus', ['id' => $created['id'], 'sort' => 7]);

        // 管理列表原样回显，供编辑抽屉回填
        $node = collect($this->flatten(
            $this->getJson('/api/system/menu/list')->assertOk()->json('data'),
        ))->firstWhere('name', 'SortedMenu');
        $this->assertSame(7, $node['sort']);

        // 运行时菜单用 sort 覆盖 meta.order
        $route = collect($this->flatten(
            $this->getJson('/api/menu/all')->assertOk()->json('data'),
        ))->firstWhere('name', 'SortedMenu');
        $this->assertSame(7, $route['meta']['order']);

        // 更新排序同样生效
        $this->putJson("/api/system/menu/{$created['id']}", [
            'name' => 'SortedMenu',
            'path' => '/sorted',
            'type' => 'menu',
            'status' => 1,
            'sort' => 3,
            'meta' => ['title' => 'sorted'],
        ])->assertOk()->assertJsonPath('data.sort', 3);

        $this->assertDatabaseHas('admin_menus', ['id' => $created['id'], 'sort' => 3]);
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

    public function test_super_role_cannot_be_disabled(): void
    {
        $this->actingAsUser('vben');
        $role = RoleModel::findByName('super', 'web');

        $this->putJson("/api/system/role/{$role->id}", [
            'name' => 'super',
            'status' => 0,
        ])->assertStatus(403);

        $this->assertSame(1, (int) $role->fresh()->status);
    }

    public function test_disabled_role_grants_no_permission_codes(): void
    {
        $this->actingAsUser('admin');
        $this->assertNotEmpty($this->getJson('/api/auth/codes')->assertOk()->json('data'));

        $role = RoleModel::findByName('admin', 'web');
        $role->update(['status' => 0]);

        $this->assertSame([], $this->getJson('/api/auth/codes')->assertOk()->json('data'));

        // 重新启用后权限码恢复
        $role->update(['status' => 1]);
        $this->assertNotEmpty($this->getJson('/api/auth/codes')->assertOk()->json('data'));
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

        $this->putJson("/api/system/user/{$jack->id}/password", ['password' => 'BrandNew-pass1'])
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertSame(0, $jack->tokens()->count());
        $this->assertTrue(Hash::check('BrandNew-pass1', $jack->fresh()->password));
    }

    public function test_batch_delete_users_skips_protected_ones(): void
    {
        $this->actingAsUser('vben');
        $vben = AdminUserModel::query()->where('username', 'vben')->firstOrFail();

        $temp = AdminUserModel::query()->create([
            'username' => 'temp-del',
            'real_name' => 'Temp',
            'password' => 'Secret123',
            'status' => 1,
        ]);

        $result = $this->postJson('/api/system/user/batch-delete', [
            'ids' => [(string) $temp->id, (string) $vben->id, '99999'],
        ])->assertOk()->assertJsonPath('code', 0)->json('data');

        // 只有可删的那条被删除
        $this->assertSame([(string) $temp->id], $result['deleted']);
        $this->assertDatabaseMissing('admin_users', ['id' => $temp->id]);
        $this->assertDatabaseHas('admin_users', ['id' => $vben->id]);

        // 每条失败都带自己的原因
        $messages = collect($result['failed'])->pluck('message', 'id')->all();
        $this->assertSame('不能删除当前登录账号。', $messages[(string) $vben->id]);
        $this->assertSame('用户不存在。', $messages['99999']);

        // 换成 admin 删唯一的 super：命中「最后一个 super」护栏
        $this->actingAsUser('admin');
        $byAdmin = $this->postJson('/api/system/user/batch-delete', [
            'ids' => [(string) $vben->id],
        ])->assertOk()->json('data');

        $this->assertSame([], $byAdmin['deleted']);
        $this->assertSame('不能删除最后一个 super 账号。', $byAdmin['failed'][0]['message']);
    }

    public function test_batch_delete_roles_skips_super_and_bound_roles(): void
    {
        $this->actingAsUser('vben');

        $super = RoleModel::findByName('super', 'web');
        $admin = RoleModel::findByName('admin', 'web'); // 有用户绑定
        $temp = RoleModel::query()->create([
            'name' => 'temp-role-del',
            'guard_name' => 'web',
            'status' => 1,
        ]);

        $result = $this->postJson('/api/system/role/batch-delete', [
            'ids' => [(string) $temp->id, (string) $super->id, (string) $admin->id],
        ])->assertOk()->json('data');

        $this->assertSame([(string) $temp->id], $result['deleted']);
        $this->assertDatabaseMissing('admin_roles', ['id' => $temp->id]);

        $messages = collect($result['failed'])->pluck('message', 'id')->all();
        $this->assertSame('super 角色不允许删除。', $messages[(string) $super->id]);
        $this->assertSame('该角色下仍有用户，无法删除。', $messages[(string) $admin->id]);
    }

    public function test_batch_delete_requires_ids(): void
    {
        $this->actingAsUser('vben');

        $this->postJson('/api/system/user/batch-delete', [])->assertStatus(422);
        $this->postJson('/api/system/role/batch-delete', ['ids' => []])->assertStatus(422);
    }

    public function test_cannot_disable_own_account(): void
    {
        $this->actingAsUser('vben');
        $vben = AdminUserModel::query()->where('username', 'vben')->firstOrFail();

        $this->putJson("/api/system/user/{$vben->id}", [
            'username' => 'vben',
            'realName' => 'Vben',
            'status' => 0,
            'roles' => ['super'],
        ])->assertStatus(403);

        $this->assertSame(1, (int) $vben->fresh()->status);
    }

    public function test_cannot_remove_own_super_role(): void
    {
        $this->actingAsUser('vben');
        $vben = AdminUserModel::query()->where('username', 'vben')->firstOrFail();

        $this->putJson("/api/system/user/{$vben->id}", [
            'username' => 'vben',
            'realName' => 'Vben',
            'status' => 1,
            'roles' => ['admin'],
        ])->assertStatus(403);

        $this->assertContains('super', $vben->fresh()->roles->pluck('name')->all());
    }

    public function test_reset_password_enforces_same_policy_as_create(): void
    {
        $this->actingAsUser('vben');
        $jack = AdminUserModel::query()->where('username', 'jack')->firstOrFail();

        // 旧策略只要 6 位，现在与创建用户共用同一套复杂度要求
        $this->putJson("/api/system/user/{$jack->id}/password", ['password' => '123456'])
            ->assertStatus(422);

        $this->putJson("/api/system/user/{$jack->id}/password", ['password' => 'Strong-pass1'])
            ->assertOk()
            ->assertJsonPath('code', 0);
    }

    public function test_disabled_account_token_is_rejected(): void
    {
        $jack = AdminUserModel::query()->where('username', 'jack')->firstOrFail();
        $tokens = app(AdminUserService::class)->issueTokens($jack);
        $this->assertSame(2, $jack->tokens()->count());

        // 直接改库停用，绕过 service 的吊销路径，模拟「令牌残留仍然有效」
        $jack->update(['status' => 0]);

        // 未吊销的 access token 也必须被 account.active 中间件拦下（而不是等到自然过期）
        $this->withHeader('Authorization', "Bearer {$tokens['accessToken']}")
            ->getJson('/api/user/info')
            ->assertStatus(401)
            ->assertJsonPath('message', '账号已被停用。');
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
        ])->assertStatus(403)->assertJsonPath('message', '没有该操作权限。');

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

    public function test_disabled_role_does_not_grant_write_access(): void
    {
        $this->actingAsUser('admin');

        // 角色启用时：admin 有部门创建权限，写接口放行
        $this->postJson('/api/system/dept', [
            'name' => 'EnabledDept',
            'pid' => '0',
            'status' => 1,
        ])->assertOk()->assertJsonPath('code', 0);

        // 停用角色：不只看不到菜单/权限码，写接口也必须被拦下（spatie 默认不看角色 status）
        RoleModel::findByName('admin', 'web')->update(['status' => 0]);

        $this->assertSame([], $this->getJson('/api/auth/codes')->assertOk()->json('data'));

        $this->postJson('/api/system/dept', [
            'name' => 'DisabledDept',
            'pid' => '0',
            'status' => 1,
        ])->assertStatus(403)->assertJsonPath('message', '没有该操作权限。');

        $this->assertDatabaseMissing('admin_depts', ['name' => 'DisabledDept']);

        // 重新启用后恢复写权限
        RoleModel::findByName('admin', 'web')->update(['status' => 1]);

        $this->postJson('/api/system/dept', [
            'name' => 'ReenabledDept',
            'pid' => '0',
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
