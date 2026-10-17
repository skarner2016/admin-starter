<?php

namespace Tests\Feature;

use App\Models\AdminUserModel;
use App\Models\DeptModel;
use App\Models\RoleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemDeptTest extends TestCase
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

    private function dept(string $name): DeptModel
    {
        return DeptModel::query()->where('name', $name)->firstOrFail();
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

    // ===================== 部门 =====================

    public function test_dept_list_returns_tree_with_string_ids(): void
    {
        $this->actingAsUser('vben');

        $tree = $this->getJson('/api/system/dept/list')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->json('data');

        $this->assertCount(1, $tree);

        $root = $tree[0];
        $this->assertSame('总公司', $root['name']);
        $this->assertSame('0', $root['pid']);
        $this->assertIsString($root['id']);
        $this->assertSame(1, $root['status']);
        // 同级按 sort 排序
        $this->assertSame(
            ['研发部', '市场部', '财务部'],
            array_column($root['children'], 'name'),
        );

        $all = collect($this->flatten($tree))->keyBy('name');
        $this->assertTrue($all->has('前端组'));
        $this->assertSame(1, $all->get('前端组')['sort']);
        $this->assertSame((string) $all->get('前端组')['pid'], $all->get('研发部')['id']);
        // 叶子节点不输出 children
        $this->assertArrayNotHasKey('children', $all->get('前端组'));
    }

    public function test_create_update_and_delete_dept(): void
    {
        $this->actingAsUser('vben');
        $parent = $this->dept('市场部');

        $created = $this->postJson('/api/system/dept', [
            'name' => '华东大区',
            'pid' => (string) $parent->id,
            'sort' => 5,
            'status' => 1,
            'remark' => '测试部门',
        ])->assertOk()->assertJsonPath('code', 0)->json('data');

        $this->assertIsString($created['id']);
        $this->assertSame((string) $parent->id, $created['pid']);
        $this->assertDatabaseHas('admin_depts', [
            'name' => '华东大区',
            'pid' => $parent->id,
            'sort' => 5,
        ]);

        $id = $created['id'];

        $updated = $this->putJson("/api/system/dept/{$id}", [
            'name' => '华南大区',
            'pid' => (string) $parent->id,
            'sort' => 6,
            'status' => 0,
        ])->assertOk()->assertJsonPath('code', 0)->json('data');

        $this->assertSame('华南大区', $updated['name']);
        $this->assertSame(0, $updated['status']);
        $this->assertDatabaseHas('admin_depts', ['id' => $id, 'name' => '华南大区', 'sort' => 6]);

        $this->deleteJson("/api/system/dept/{$id}")->assertOk()->assertJsonPath('code', 0);
        $this->assertDatabaseMissing('admin_depts', ['id' => $id]);
    }

    public function test_same_level_duplicate_name_is_rejected(): void
    {
        $this->actingAsUser('vben');
        $root = $this->dept('总公司');

        // 总公司下已有研发部
        $this->postJson('/api/system/dept', [
            'name' => '研发部',
            'pid' => (string) $root->id,
            'status' => 1,
        ])->assertStatus(422);
    }

    public function test_same_name_under_different_parent_is_allowed(): void
    {
        $this->actingAsUser('vben');
        $parent = $this->dept('市场部');

        $this->postJson('/api/system/dept', [
            'name' => '研发部',
            'pid' => (string) $parent->id,
            'status' => 1,
        ])->assertOk()->assertJsonPath('code', 0);
    }

    public function test_dept_name_exists_endpoint_is_scoped_to_same_parent(): void
    {
        $this->actingAsUser('vben');
        $root = $this->dept('总公司');
        $market = $this->dept('市场部');
        $dev = $this->dept('研发部');

        // 中文必须按浏览器的方式编码进 query，直接拼裸中文会被 URI 处理破坏
        $query = fn (array $params) => '/api/system/dept/name-exists?'.http_build_query($params);

        $this->getJson($query(['name' => '研发部', 'pid' => (string) $root->id]))
            ->assertOk()->assertJsonPath('data', true);

        $this->getJson($query(['name' => '研发部', 'pid' => (string) $market->id]))
            ->assertOk()->assertJsonPath('data', false);

        // 排除自身（编辑回显场景）
        $this->getJson($query(['name' => '研发部', 'pid' => (string) $root->id, 'id' => (string) $dev->id]))
            ->assertOk()->assertJsonPath('data', false);
    }

    public function test_delete_dept_with_children_is_rejected(): void
    {
        $this->actingAsUser('vben');
        $dev = $this->dept('研发部');

        $this->deleteJson("/api/system/dept/{$dev->id}")
            ->assertOk()
            ->assertJsonPath('code', -1);

        $this->assertDatabaseHas('admin_depts', ['id' => $dev->id]);
    }

    public function test_delete_dept_with_users_is_rejected(): void
    {
        $this->actingAsUser('vben');
        // jack 挂在「前端组」下，该部门没有子部门
        $frontend = $this->dept('前端组');
        $this->assertSame(1, $frontend->status);

        $this->deleteJson("/api/system/dept/{$frontend->id}")
            ->assertOk()
            ->assertJsonPath('code', -1);

        $this->assertDatabaseHas('admin_depts', ['id' => $frontend->id]);
    }

    public function test_cannot_set_parent_to_self_or_descendant(): void
    {
        $this->actingAsUser('vben');
        $root = $this->dept('总公司');
        $dev = $this->dept('研发部');

        // 自己
        $this->putJson("/api/system/dept/{$dev->id}", [
            'name' => '研发部',
            'pid' => (string) $dev->id,
            'status' => 1,
        ])->assertStatus(422);

        // 自己的子孙
        $this->putJson("/api/system/dept/{$root->id}", [
            'name' => '总公司',
            'pid' => (string) $dev->id,
            'status' => 1,
        ])->assertStatus(422);

        $this->assertSame(0, $root->fresh()->pid);
    }

    public function test_update_rejects_unknown_parent(): void
    {
        $this->actingAsUser('vben');
        $market = $this->dept('市场部');

        $this->putJson("/api/system/dept/{$market->id}", [
            'name' => '市场部',
            'pid' => '99999',
            'status' => 1,
        ])->assertStatus(422);
    }

    public function test_dept_button_menus_derive_permissions(): void
    {
        $this->assertDatabaseHas('admin_permissions', ['name' => 'System:Dept:Create', 'guard_name' => 'web']);
        $this->assertDatabaseHas('admin_permissions', ['name' => 'System:Dept:Edit', 'guard_name' => 'web']);
        $this->assertDatabaseHas('admin_permissions', ['name' => 'System:Dept:Delete', 'guard_name' => 'web']);

        // super 与 admin 拿到按钮码，user 没有
        $this->assertContains(
            'System:Dept:Create',
            RoleModel::findByName('super', 'web')->permissions->pluck('name')->all(),
        );
        $this->assertContains(
            'System:Dept:Delete',
            RoleModel::findByName('admin', 'web')->permissions->pluck('name')->all(),
        );
        $this->assertNotContains(
            'System:Dept:Create',
            RoleModel::findByName('user', 'web')->permissions->pluck('name')->all(),
        );
    }

    public function test_reads_require_only_authentication_but_writes_require_permission(): void
    {
        // user 角色没有任何按钮权限码
        $this->actingAsUser('jack');

        $this->getJson('/api/system/dept/list')->assertOk()->assertJsonPath('code', 0);

        $this->postJson('/api/system/dept', [
            'name' => '偷加部门',
            'status' => 1,
        ])->assertStatus(403);

        $this->assertDatabaseMissing('admin_depts', ['name' => '偷加部门']);
    }

    // ===================== 用户 ↔ 部门 =====================

    public function test_user_list_filters_by_dept_including_descendants(): void
    {
        $this->actingAsUser('vben');
        $root = $this->dept('总公司');
        $dev = $this->dept('研发部');
        $frontend = $this->dept('前端组');

        // 不传 deptId：全部
        $this->getJson('/api/system/user/list?page=1&pageSize=10')
            ->assertOk()
            ->assertJsonPath('data.total', 3);

        // 研发部：含子部门「前端组」→ admin + jack
        $items = $this->getJson("/api/system/user/list?page=1&pageSize=10&deptId={$dev->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->json('data.items');

        $this->assertEqualsCanonicalizing(
            ['admin', 'jack'],
            array_column($items, 'username'),
        );

        // 总公司：含全部子孙 → 3 个
        $this->getJson("/api/system/user/list?page=1&pageSize=10&deptId={$root->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 3);

        // 前端组：只有 jack
        $this->getJson("/api/system/user/list?page=1&pageSize=10&deptId={$frontend->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.username', 'jack');
    }

    public function test_user_resource_exposes_dept_id_and_name(): void
    {
        $this->actingAsUser('vben');
        $dev = $this->dept('研发部');

        $items = $this->getJson('/api/system/user/list?page=1&pageSize=10')
            ->assertOk()
            ->json('data.items');

        $admin = collect($items)->firstWhere('username', 'admin');
        $this->assertSame((string) $dev->id, $admin['deptId']);
        $this->assertSame('研发部', $admin['deptName']);

        // 未分配部门时返回空字符串而不是 null，便于前端表单绑定
        $jack = AdminUserModel::query()->where('username', 'jack')->firstOrFail();
        $jack->update(['dept_id' => null]);

        $items = $this->getJson('/api/system/user/list?page=1&pageSize=10')->json('data.items');
        $jackRow = collect($items)->firstWhere('username', 'jack');
        $this->assertSame('', $jackRow['deptId']);
        $this->assertSame('', $jackRow['deptName']);
    }

    public function test_user_create_and_update_store_dept_id(): void
    {
        $this->actingAsUser('vben');
        $dev = $this->dept('研发部');

        $created = $this->postJson('/api/system/user', [
            'username' => 'dept-newbie',
            'realName' => 'Dept Newbie',
            'password' => 'Secret123',
            'status' => 1,
            'roles' => ['user'],
            'deptId' => (string) $dev->id,
        ])->assertOk()->assertJsonPath('code', 0)->json('data');

        $this->assertSame((string) $dev->id, $created['deptId']);
        $this->assertSame('研发部', $created['deptName']);

        $id = $created['id'];

        // 清空部门：0 与空字符串都视为未分配
        $this->putJson("/api/system/user/{$id}", [
            'username' => 'dept-newbie',
            'realName' => 'Dept Newbie',
            'status' => 1,
            'roles' => ['user'],
            'deptId' => '0',
        ])->assertOk()->assertJsonPath('data.deptId', '');

        $this->assertNull(AdminUserModel::query()->where('username', 'dept-newbie')->value('dept_id'));

        $this->putJson("/api/system/user/{$id}", [
            'username' => 'dept-newbie',
            'realName' => 'Dept Newbie',
            'status' => 1,
            'roles' => ['user'],
            'deptId' => '',
        ])->assertOk()->assertJsonPath('data.deptId', '');
    }

    public function test_user_rejects_unknown_dept(): void
    {
        $this->actingAsUser('vben');

        $this->postJson('/api/system/user', [
            'username' => 'ghost-dept',
            'realName' => 'Ghost',
            'password' => 'Secret123',
            'status' => 1,
            'deptId' => '99999',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('admin_users', ['username' => 'ghost-dept']);
    }
}
