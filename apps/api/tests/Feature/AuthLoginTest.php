<?php

namespace Tests\Feature;

use App\Models\AdminUserModel;
use App\Models\LoginLogModel;
use App\Services\AdminUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_login_success_returns_access_token_and_sets_refresh_cookie(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'vben',
            'password' => '123456',
            // 前端登录页附带的字段，不应导致 422
            'captcha' => true,
            'selectAccount' => 'vben',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', 'ok')
            ->assertJsonPath('error', null)
            ->assertJsonStructure(['code', 'data' => ['accessToken'], 'message', 'error'])
            ->assertCookie(AdminUserService::COOKIE_NAME);

        $this->assertNotEmpty($response->json('data.accessToken'));
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        $this->postJson('/api/auth/login', [
            'username' => 'vben',
            'password' => 'wrong-password',
        ])->assertStatus(401)->assertJsonPath('code', 401);

        $this->assertDatabaseHas('admin_login_logs', [
            'username' => 'vben',
            'success' => false,
            'reason' => 'invalid_credentials',
        ]);
    }

    public function test_login_with_unknown_username_returns_401_without_revealing_existence(): void
    {
        $this->postJson('/api/auth/login', [
            'username' => 'nobody',
            'password' => '123456',
        ])->assertStatus(401);
    }

    public function test_login_without_required_fields_returns_422(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 422)
            ->assertJsonStructure(['error' => ['username', 'password']]);
    }

    public function test_disabled_account_returns_403(): void
    {
        AdminUserModel::query()->where('username', 'jack')->update(['status' => 0]);

        $this->postJson('/api/auth/login', [
            'username' => 'jack',
            'password' => '123456',
        ])->assertStatus(403)->assertJsonPath('code', 403);

        $this->assertDatabaseHas('admin_login_logs', [
            'username' => 'jack',
            'success' => false,
            'reason' => 'disabled',
        ]);
    }

    public function test_account_is_locked_after_repeated_failures(): void
    {
        foreach (range(1, AdminUserService::MAX_FAILED_ATTEMPTS) as $ignored) {
            LoginLogModel::query()->create([
                'username' => 'vben',
                'success' => false,
                'reason' => 'invalid_credentials',
                'created_at' => now(),
            ]);
        }

        // 正确密码也会被锁定拦下
        $this->postJson('/api/auth/login', [
            'username' => 'vben',
            'password' => '123456',
        ])->assertStatus(423)->assertJsonPath('code', 423);
    }

    public function test_successful_login_resets_the_failure_window(): void
    {
        foreach (range(1, AdminUserService::MAX_FAILED_ATTEMPTS) as $ignored) {
            LoginLogModel::query()->create([
                'username' => 'vben',
                'success' => false,
                'reason' => 'invalid_credentials',
                'created_at' => now()->subMinutes(AdminUserService::LOCK_MINUTES + 1),
            ]);
        }

        // 失败发生在锁定窗口之外，不应被锁定
        $this->postJson('/api/auth/login', [
            'username' => 'vben',
            'password' => '123456',
        ])->assertOk();

        $this->assertDatabaseHas('admin_login_logs', [
            'username' => 'vben',
            'success' => true,
        ]);
    }

    public function test_protected_endpoints_require_authentication(): void
    {
        $this->getJson('/api/user/info')->assertStatus(401)->assertJsonPath('code', 401);
        $this->getJson('/api/auth/codes')->assertStatus(401)->assertJsonPath('code', 401);
        $this->getJson('/api/menu/all')->assertStatus(401)->assertJsonPath('code', 401);
    }

    public function test_access_token_from_login_can_read_user_info(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'username' => 'vben',
            'password' => '123456',
        ])->assertOk();

        $accessToken = $login->json('data.accessToken');

        $this->withHeader('Authorization', "Bearer {$accessToken}")
            ->getJson('/api/user/info')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.username', 'vben')
            ->assertJsonPath('data.realName', 'Vben')
            // homePath 为硬编码常量，所有用户一致
            ->assertJsonPath('data.homePath', AdminUserService::HOME_PATH)
            ->assertJsonPath('data.roles', ['super']);
    }

    public function test_codes_returns_all_permissions_for_super_role(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'username' => 'vben',
            'password' => '123456',
        ])->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$login->json('data.accessToken'))
            ->getJson('/api/auth/codes')
            ->assertOk()
            ->assertJsonPath('code', 0)
            // 系统管理 3 个页面 + 部门页，各 3 个按钮权限码
            ->assertJsonCount(12, 'data');
    }

    public function test_codes_returns_only_own_permissions_for_normal_role(): void
    {
        // jack 未被授予系统管理菜单，因此没有任何按钮权限码
        $login = $this->postJson('/api/auth/login', [
            'username' => 'jack',
            'password' => '123456',
        ])->assertOk();

        $codes = $this->withHeader('Authorization', 'Bearer '.$login->json('data.accessToken'))
            ->getJson('/api/auth/codes')
            ->assertOk()
            ->json('data');

        $this->assertSame([], $codes);
    }

    public function test_codes_are_derived_from_button_menus(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => '123456',
        ])->assertOk();

        $codes = $this->withHeader('Authorization', 'Bearer '.$login->json('data.accessToken'))
            ->getJson('/api/auth/codes')
            ->assertOk()
            ->json('data');

        $this->assertCount(12, $codes);
        $this->assertContains('System:User:Create', $codes);
        $this->assertContains('System:Dept:Create', $codes);
    }

    public function test_password_is_stored_hashed(): void
    {
        $user = AdminUserModel::query()->where('username', 'vben')->firstOrFail();

        $this->assertNotSame('123456', $user->password);
        $this->assertTrue(Hash::check('123456', $user->password));
    }
}
