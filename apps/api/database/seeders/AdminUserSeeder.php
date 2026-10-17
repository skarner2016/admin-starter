<?php

namespace Database\Seeders;

use App\Models\AdminUserModel;
use App\Models\DeptModel;
use App\Models\RoleModel;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * 角色。权限码不在这里授予 —— 权限由菜单表 type=button 行的 auth_code 派生，
     * 见 MenuSeeder 与 RoleService::syncMenusAndPermissions()。
     */
    private const ROLES = [
        ['name' => 'super', 'remark' => '超级管理员，拥有全部权限'],
        ['name' => 'admin', 'remark' => '管理员'],
        ['name' => 'user', 'remark' => '普通用户'],
    ];

    private const USERS = [
        ['username' => 'vben', 'real_name' => 'Vben', 'role' => 'super', 'dept' => '总公司'],
        ['username' => 'admin', 'real_name' => 'Admin', 'role' => 'admin', 'dept' => '研发部'],
        ['username' => 'jack', 'real_name' => 'Jack', 'role' => 'user', 'dept' => '前端组'],
    ];

    public function run(): void
    {
        foreach (self::ROLES as $item) {
            $role = RoleModel::findOrCreate($item['name'], 'web');
            $role->update(['remark' => $item['remark'], 'status' => 1]);
        }

        foreach (self::USERS as $item) {
            $user = AdminUserModel::query()->updateOrCreate(
                ['username' => $item['username']],
                [
                    'real_name' => $item['real_name'],
                    'password' => '123456',
                    'dept_id' => DeptModel::query()->where('name', $item['dept'])->value('id'),
                    'status' => 1,
                ],
            );

            $user->syncRoles([$item['role']]);
        }
    }
}
