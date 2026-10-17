<?php

namespace Database\Seeders;

use App\Models\DeptModel;
use Illuminate\Database\Seeder;

class DeptSeeder extends Seeder
{
    /**
     * 示例部门树。
     *
     * 用 firstOrCreate 保证可重复执行 —— 不像 MenuSeeder 会先清空重建，
     * 否则会把管理员在后台维护的部门结构覆盖掉。
     *
     * @var array<int, array<string, mixed>>
     */
    private const DEPTS = [
        [
            'name' => '总公司',
            'sort' => 1,
            'children' => [
                [
                    'name' => '研发部',
                    'sort' => 1,
                    'children' => [
                        ['name' => '前端组', 'sort' => 1],
                        ['name' => '后端组', 'sort' => 2],
                    ],
                ],
                ['name' => '市场部', 'sort' => 2],
                ['name' => '财务部', 'sort' => 3],
            ],
        ],
    ];

    public function run(): void
    {
        $this->createTree(self::DEPTS, 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function createTree(array $nodes, int $pid): void
    {
        foreach ($nodes as $node) {
            $dept = DeptModel::query()->firstOrCreate(
                ['pid' => $pid, 'name' => $node['name']],
                ['sort' => $node['sort'], 'status' => 1],
            );

            $this->createTree($node['children'] ?? [], $dept->id);
        }
    }
}
