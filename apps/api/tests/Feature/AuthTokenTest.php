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

    public function test_revoke_all_refresh_tokens_invalidates_sessions(): void
    {
        $session = $this->login();
        $user = AdminUserModel::query()->where('username', 'vben')->firstOrFail();

        $revoked = app(AdminUserService::class)->revokeAllRefreshTokens($user);

        $this->assertGreaterThan(0, $revoked);

        $this->withRefreshCookie($session['refreshToken'])
            ->postJson('/api/auth/refresh')
            ->assertStatus(401);
    }

    public function test_tokens_are_stored_in_prefixed_table(): void
    {
        $this->login();

        // access + refresh 各一条，且写入的是加前缀后的表
        $this->assertDatabaseCount('admin_personal_access_tokens', 2);
    }
}
