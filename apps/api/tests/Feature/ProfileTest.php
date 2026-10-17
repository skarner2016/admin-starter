<?php

namespace Tests\Feature;

use App\Models\AdminUserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    /** 1x1 PNG：环境没装 GD 也能通过 image 校验 */
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function actingAsUser(string $username): AdminUserModel
    {
        $user = AdminUserModel::query()->where('username', $username)->firstOrFail();
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function fakePng(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, base64_decode(self::PNG_BASE64, true));

        return new UploadedFile($path, 'avatar.png', 'image/png', null, true);
    }

    // ===================== 上传 =====================

    public function test_upload_stores_image_and_returns_url(): void
    {
        Storage::fake('public');
        $this->actingAsUser('jack');

        $url = $this->post('/api/upload', ['file' => $this->fakePng()])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->json('data.url');

        $this->assertStringStartsWith('/storage/uploads/', $url);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $url));
    }

    public function test_upload_rejects_non_image(): void
    {
        Storage::fake('public');
        $this->actingAsUser('jack');

        $this->post('/api/upload', [
            'file' => UploadedFile::fake()->create('evil.txt', 1, 'text/plain'),
        ])->assertStatus(422);
    }

    public function test_upload_requires_authentication(): void
    {
        Storage::fake('public');

        $this->post('/api/upload', ['file' => $this->fakePng()])->assertStatus(401);
    }

    // ===================== 自助改资料 =====================

    public function test_profile_update_only_touches_own_profile(): void
    {
        $jack = $this->actingAsUser('jack');

        $response = $this->putJson('/api/user/profile', [
            'realName' => 'Jack New',
            'avatar' => '/storage/uploads/20261017/avatar.png',
            'description' => '个人简介',
        ])->assertOk()->assertJsonPath('code', 0);

        $this->assertSame('Jack New', $response->json('data.realName'));
        $this->assertSame('/storage/uploads/20261017/avatar.png', $response->json('data.avatar'));

        $fresh = $jack->fresh();
        $this->assertSame('Jack New', $fresh->real_name);
        $this->assertSame('个人简介', $fresh->description);

        // 用户名与角色不可通过该接口修改
        $this->assertSame('jack', $fresh->username);
        $this->assertSame(['user'], $fresh->roles->pluck('name')->all());
    }

    public function test_profile_update_requires_real_name(): void
    {
        $this->actingAsUser('jack');

        $this->putJson('/api/user/profile', ['realName' => ''])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['realName']]);
    }

    // ===================== 自助改密 =====================

    public function test_self_password_change_keeps_current_session_and_revokes_others(): void
    {
        $login = $this->postJson('/api/auth/login', ['username' => 'jack', 'password' => '123456'])->assertOk();
        $token = $login->json('data.accessToken');

        $jack = AdminUserModel::query()->where('username', 'jack')->firstOrFail();
        // 登录签发 access + refresh 两个令牌
        $this->assertSame(2, $jack->tokens()->count());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/user/password', [
                'oldPassword' => '123456',
                'password' => 'BrandNew-pass1',
            ])->assertOk()->assertJsonPath('code', 0);

        // 只剩当前 access token，refresh 及其它会话已失效
        $this->assertSame(1, $jack->tokens()->count());
        $this->assertTrue(Hash::check('BrandNew-pass1', $jack->fresh()->password));

        // 旧密码失效，新密码可用
        $this->postJson('/api/auth/login', ['username' => 'jack', 'password' => '123456'])->assertStatus(401);
        $this->postJson('/api/auth/login', ['username' => 'jack', 'password' => 'BrandNew-pass1'])->assertOk();
    }

    public function test_self_password_change_validates_old_password(): void
    {
        $this->actingAsUser('jack');

        $this->putJson('/api/user/password', [
            'oldPassword' => 'wrong-old-pass',
            'password' => 'BrandNew-pass1',
        ])->assertStatus(422)->assertJsonPath('message', '当前密码不正确。');
    }

    public function test_self_password_change_enforces_policy_and_difference(): void
    {
        $this->actingAsUser('jack');

        // 弱密码
        $this->putJson('/api/user/password', [
            'oldPassword' => '123456',
            'password' => '12345678',
        ])->assertStatus(422);

        // 与旧密码相同
        $this->putJson('/api/user/password', [
            'oldPassword' => '123456',
            'password' => '123456',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('123456', AdminUserModel::query()->where('username', 'jack')->firstOrFail()->password));
    }
}
