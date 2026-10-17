<?php

namespace Tests\Feature;

use App\Models\AdminUserModel;
use App\Models\MenuModel;
use App\Services\AdminUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MenuAccessTest extends TestCase
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

    public function test_super_role_receives_all_menus(): void
    {
        $this->actingAsUser('vben');

        $response = $this->getJson('/api/menu/all')->assertOk()->assertJsonPath('code', 0);

        $menus = $response->json('data');

        $this->assertCount(5, $menus);
        $this->assertSame(
            ['Dashboard', 'Demos', 'System', 'Profile', 'VbenAbout'],
            array_column($menus, 'name'),
        );
    }

    public function test_normal_role_receives_only_assigned_menus(): void
    {
        $this->actingAsUser('jack');

        $menus = $this->getJson('/api/menu/all')->assertOk()->json('data');

        // 未授予 Demos
        $this->assertSame(
            ['Dashboard', 'Profile', 'VbenAbout'],
            array_column($menus, 'name'),
        );
    }

    public function test_every_node_has_a_name_and_meta(): void
    {
        $this->actingAsUser('vben');

        $menus = $this->getJson('/api/menu/all')->assertOk()->json('data');

        $this->assertNodesAreValid($menus);
    }

    public function test_button_and_disabled_menus_are_filtered_out(): void
    {
        MenuModel::query()->create([
            'pid' => 0,
            'name' => 'HiddenButtonOnly',
            'path' => '/hidden-button',
            'type' => MenuModel::TYPE_BUTTON,
            'auth_code' => 'AC_HIDDEN',
            'status' => 1,
            'sort' => 1,
        ]);

        MenuModel::query()->create([
            'pid' => 0,
            'name' => 'DisabledCatalog',
            'path' => '/disabled',
            'type' => MenuModel::TYPE_CATALOG,
            'status' => 0,
            'sort' => 2,
        ]);

        $this->actingAsUser('vben');

        $names = array_column($this->getJson('/api/menu/all')->assertOk()->json('data'), 'name');

        $this->assertNotContains('HiddenButtonOnly', $names);
        $this->assertNotContains('DisabledCatalog', $names);
    }

    public function test_child_only_grant_still_includes_ancestor_catalog(): void
    {
        $workspace = MenuModel::query()->where('name', 'Workspace')->firstOrFail();
        $user = AdminUserModel::query()->where('username', 'jack')->firstOrFail();

        // 只授子菜单，不授父级 Dashboard
        DB::table('admin_role_menu')
            ->where('role_id', $user->roles->first()->id)
            ->where('menu_id', '!=', $workspace->id)
            ->delete();

        $this->actingAsUser('jack');

        $menus = $this->getJson('/api/menu/all')->assertOk()->json('data');

        $this->assertSame('Dashboard', $menus[0]['name']);
        $this->assertSame('Workspace', $menus[0]['children'][0]['name']);
    }

    public function test_home_path_is_identical_for_every_user(): void
    {
        foreach (['vben', 'admin', 'jack'] as $username) {
            $this->actingAsUser($username);

            $this->getJson('/api/user/info')
                ->assertOk()
                ->assertJsonPath('data.homePath', AdminUserService::HOME_PATH);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function assertNodesAreValid(array $nodes): void
    {
        foreach ($nodes as $node) {
            $this->assertArrayHasKey('name', $node);
            $this->assertNotEmpty($node['name']);
            $this->assertArrayHasKey('path', $node);
            $this->assertArrayHasKey('meta', $node);
            $this->assertArrayHasKey('order', $node['meta']);

            if (isset($node['component'])) {
                $this->assertStringStartsWith('/', $node['component']);
            }

            if (isset($node['children'])) {
                $this->assertNodesAreValid($node['children']);
            }
        }
    }
}
