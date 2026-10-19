<?php

namespace Tests\Feature;

use App\Models\AdminUserModel;
use App\Services\AdminUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function login(string $username = 'vben'): array
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => $username,
            'password' => '123456',
        ])->assertOk();

        return [
            'accessToken' => $response->json('data.accessToken'),
            'refreshToken' => $response->getCookie(AdminUserService::COOKIE_NAME, false)->getValue(),
        ];
    }

    /**
     * 注意：Json 请求默认不携带 Cookie，必须显式 withCredentials()，
     * 与前端 refreshTokenApi 的 withCredentials: true 对应。
     */
    private function withRefreshCookie(string $refreshToken): static
    {
        return $this
            ->withUnencryptedCookie(AdminUserService::COOKIE_NAME, $refreshToken)
            ->withCredentials();
    }

    public function test_refresh_returns_bare_token_string_and_rotates_refresh_cookie(): void
    {
        $session = $this->login();

        $response = $this->withRefreshCookie($session['refreshToken'])
            ->postJson('/api/auth/refresh')
            ->assertOk();

        // 前端取 resp.data 直接当 token，因此 body 必须是裸字符串而不是统一响应包
        $body = $response->getContent();
        $this->assertStringNotContainsString('"code"', $body);
        $this->assertNotEmpty(trim($body));

        $rotated = $response->getCookie(AdminUserService::COOKIE_NAME, false)->getValue();
        $this->assertNotSame($session['refreshToken'], $rotated);
    }

    public function test_refresh_token_cannot_be_reused_after_rotation(): void
    {
        $session = $this->login();

        $this->withRefreshCookie($session['refreshToken'])
            ->postJson('/api/auth/refresh')
            ->assertOk();

        // 重放旧 refresh token 必须被拒
        $this->withRefreshCookie($session['refreshToken'])
            ->postJson('/api/auth/refresh')
            ->assertStatus(401);
    }

    public function test_refresh_without_cookie_returns_401(): void
    {
        $this->postJson('/api/auth/refresh')->assertStatus(401)->assertJsonPath('code', 401);
    }

    public function test_access_token_works_after_refresh(): void
    {
        $session = $this->login();

        $newAccessToken = trim(
            $this->withRefreshCookie($session['refreshToken'])
                ->postJson('/api/auth/refresh')
                ->assertOk()
                ->getContent(),
        );

        $this->withHeader('Authorization', "Bearer {$newAccessToken}")
            ->getJson('/api/user/info')
            ->assertOk()
            ->assertJsonPath('data.username', 'vben');
    }

    public function test_logout_revokes_refresh_token_and_clears_cookie(): void
    {
        $session = $this->login();

        $this->withRefreshCookie($session['refreshToken'])
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertCookieExpired(AdminUserService::COOKIE_NAME);

        // 已吊销的 refresh token 不能再换 token
        $this->withRefreshCookie($session['refreshToken'])
            ->postJson('/api/auth/refresh')
            ->assertStatus(401);
    }

    public function test_logout_revokes_the_paired_access_token(): void
    {
        $session = $this->login();

        $this->withRefreshCookie($session['refreshToken'])
            ->postJson('/api/auth/logout')
            ->assertOk();

        // access 与 refresh 同属一个会话，登出后必须一起消失
        $this->assertSame(
            0,
            AdminUserModel::query()->where('username', 'vben')->firstOrFail()->tokens()->count(),
        );

        // 登出后 access token 必须立即失效，而不是拖到自然过期
        $this->withHeader('Authorization', "Bearer {$session['accessToken']}")
            ->getJson('/api/user/info')
            ->assertStatus(401);
    }

    public function test_logout_only_revokes_its_own_session(): void
    {
        $first = $this->login();
        $second = $this->login();

        $this->withRefreshCookie($first['refreshToken'])
            ->postJson('/api/auth/logout')
            ->assertOk();

        // 另一个登录会话的 access token 不应被牵连
        $this->withHeader('Authorization', "Bearer {$second['accessToken']}")
            ->getJson('/api/user/info')
            ->assertOk();
    }

    public function test_refresh_rotates_within_the_same_session(): void
    {
        $session = $this->login();

        $response = $this->withRefreshCookie($session['refreshToken'])
            ->postJson('/api/auth/refresh')
            ->assertOk();

        $rotatedAccessToken = trim($response->getContent());
        $rotatedRefreshToken = $response->getCookie(AdminUserService::COOKIE_NAME, false)->getValue();

        // 轮换后仍属同一会话：用新 refresh 登出也要能吊销新 access
        $this->withRefreshCookie($rotatedRefreshToken)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$rotatedAccessToken}")
            ->getJson('/api/user/info')
            ->assertStatus(401);
    }

    public function test_refresh_rejects_disabled_account_and_revokes_tokens(): void
    {
        $session = $this->login();
        $user = AdminUserModel::query()->where('username', 'vben')->firstOrFail();

        // 直接改库，模拟「账号已停用但令牌没被清理」的情形（service 的停用路径会吊销令牌）
        $user->update(['status' => 0]);

        $this->withRefreshCookie($session['refreshToken'])
            ->postJson('/api/auth/refresh')
            ->assertStatus(401)
            ->assertJsonPath('code', 401);

        // 残留令牌被一并清理，不能再被反复用来刷新
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_tokens_are_stored_in_prefixed_table(): void
    {
        $this->login();

        // access + refresh 各一条，且写入的是加前缀后的表
        $this->assertDatabaseCount('admin_personal_access_tokens', 2);
    }
}
