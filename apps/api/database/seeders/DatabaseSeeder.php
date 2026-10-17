<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 顺序有依赖：角色先建（MenuSeeder 要按角色名授权）、部门先建（AdminUserSeeder 要挂部门）
        $this->call([
            DeptSeeder::class,
            AdminUserSeeder::class,
            MenuSeeder::class,
            // 部门菜单独立成幂等 seeder：MenuSeeder 会清空菜单与授权，不能靠重跑它来加部门
            DeptMenuSeeder::class,
        ]);
    }
}
