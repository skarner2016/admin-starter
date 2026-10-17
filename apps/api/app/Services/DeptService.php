<?php

namespace App\Services;

use App\Http\Resources\SystemDeptResource;
use App\Models\AdminUserModel;
use App\Models\DeptModel;
use Illuminate\Support\Collection;

class DeptService
{
    /**
     * 部门管理用的完整树（/system/dept/list 出参）。
     * 一次取全表在内存建树，避免逐层查库。
     */
    public function tree(): array
    {
        return $this->buildTree(
            DeptModel::query()->orderBy('sort')->orderBy('id')->get(),
            0,
        );
    }

    public function create(array $data): DeptModel
    {
        return DeptModel::query()->create($data);
    }

    public function update(DeptModel $dept, array $data): DeptModel
    {
        $dept->update($data);

        return $dept;
    }

    public function delete(DeptModel $dept): void
    {
        $dept->delete();
    }

    public function hasChildren(DeptModel $dept): bool
    {
        return DeptModel::query()->where('pid', $dept->id)->exists();
    }

    public function userCount(DeptModel $dept): int
    {
        return AdminUserModel::query()->where('dept_id', $dept->id)->count();
    }

    /**
     * 同级同名校验（不同父级允许同名）。
     */
    public function nameExists(string $name, ?int $pid, ?int $excludeId = null): bool
    {
        return DeptModel::query()
            ->where('name', $name)
            ->where('pid', $pid ?? 0)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->exists();
    }

    /**
     * 该部门及其所有子孙部门的 id 集合。
     * 用于「按部门（含子部门）过滤用户」与「上级部门环路校验」。
     *
     * @return array<int, int>
     */
    public function descendantIds(int $deptId): array
    {
        $byParent = [];

        foreach (DeptModel::query()->pluck('pid', 'id') as $id => $pid) {
            $byParent[(int) $pid][] = (int) $id;
        }

        $result = [];
        $queue = [$deptId];

        while ($queue !== []) {
            $current = array_shift($queue);

            if (in_array($current, $result, true)) {
                continue;
            }

            $result[] = $current;

            foreach ($byParent[$current] ?? [] as $child) {
                $queue[] = $child;
            }
        }

        return $result;
    }

    /**
     * 把 pid 指向自己或自己的子孙会形成环，建树时无限递归。
     */
    public function wouldCreateCycle(DeptModel $dept, int $pid): bool
    {
        if ($pid === 0) {
            return false;
        }

        return in_array($pid, $this->descendantIds($dept->id), true);
    }

    /**
     * @param  Collection<int, DeptModel>  $depts
     */
    private function buildTree(Collection $depts, int $pid): array
    {
        $tree = [];

        foreach ($depts as $dept) {
            if ($dept->pid !== $pid) {
                continue;
            }

            $node = (new SystemDeptResource($dept))->toArray(request());
            $children = $this->buildTree($depts, $dept->id);

            if ($children !== []) {
                $node['children'] = $children;
            }

            $tree[] = $node;
        }

        return $tree;
    }
}
