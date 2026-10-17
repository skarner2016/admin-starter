<?php

namespace Tests\Feature;

use App\Models\AdminUserModel;
use App\Models\RoleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemLoginLogTest extends TestCase
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

    public function test_login_log_list_returns_pagination_with_filters(): void
    {
        // 通过真实登录接口造日志：一条成功、一条密码错误
        $this->postJson('/api/auth/login', ['username' => 'vben', 'password' => '123456'])->assertOk();
        $this->postJson('/api/auth/login', ['username' => 'vben', 'password' => 'wrong-pass'])->assertStatus(401);

        $this->actingAsUser('vben');

        $response = $this->getJson('/api/system/login-log/list?page=1&pageSize=20')
            ->assertOk()
            ->assertJsonPath('code', 0);

        $items = $response->json('data.items');
        $this->assertSame(2, $response->json('data.total'));
        $this->assertIsString($items[0]['id']);
        $this->assertIsBool($items[0]['success']);
        $this->assertArrayHasKey('userAgent', $items[0]);

        // 只看失败：reason 为原始原因码，展示文案由前端翻译
        $failed = $this->getJson('/api/system/login-log/list?page=1&pageSize=20&success=0')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $failed['total']);
        $this->assertSame('invalid_credentials', $failed['items'][0]['reason']);
        $this->assertFalse($failed['items'][0]['success']);

        // 按用户名过滤
        $this->getJson('/api/system/login-log/list?username=nobody')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_login_log_read_requires_permission_code(): void
    {
        // user 角色没有 System:LoginLog:View：日志含 IP/UA，读接口也要鉴权
        $this->actingAsUser('jack');

        $this->getJson('/api/system/login-log/list')
            ->assertStatus(403)
            ->assertJsonPath('message', '没有该操作权限。');
    }

    public function test_login_log_menu_and_view_code_are_seeded(): void
    {
        $this->assertDatabaseHas('admin_permissions', [
            'name' => 'System:LoginLog:View',
            'guard_name' => 'web',
        ]);

        $this->assertContains(
            'System:LoginLog:View',
            RoleModel::findByName('admin', 'web')->permissions->pluck('name')->all(),
        );

        $this->assertNotContains(
            'System:LoginLog:View',
            RoleModel::findByName('user', 'web')->permissions->pluck('name')->all(),
        );
    }

    public function test_illegal_time_filter_is_ignored(): void
    {
        $this->postJson('/api/auth/login', ['username' => 'vben', 'password' => '123456'])->assertOk();

        $this->actingAsUser('vben');

        // 非法时间串不应导致 SQL 报错，只是不生效
        $this->getJson('/api/system/login-log/list?startTime=not-a-date')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }
}
