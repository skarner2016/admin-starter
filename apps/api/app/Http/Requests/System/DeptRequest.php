<?php

namespace App\Http\Requests\System;

use App\Models\DeptModel;
use App\Services\DeptService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class DeptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:64',
                function (string $attribute, mixed $value, Closure $fail) {
                    $dept = $this->route('dept');

                    if (app(DeptService::class)->nameExists(
                        (string) $value,
                        $this->pid(),
                        $dept instanceof DeptModel ? $dept->id : null,
                    )) {
                        $fail('同级下已存在同名部门。');
                    }
                },
            ],
            'pid' => [
                'nullable',
                'integer',
                'min:0',
                function (string $attribute, mixed $value, Closure $fail) {
                    $pid = (int) $value;

                    if ($pid === 0) {
                        return;
                    }

                    $dept = $this->route('dept');

                    // 指向自己或自己的子孙会形成环
                    if ($dept instanceof DeptModel
                        && app(DeptService::class)->wouldCreateCycle($dept, $pid)
                    ) {
                        $fail('上级部门不能是自己或自己的下级部门。');

                        return;
                    }

                    $parent = DeptModel::query()->find($pid);

                    if (! $parent) {
                        $fail('上级部门不存在。');

                        return;
                    }

                    // 仅当上级发生变化时校验启用状态：否则原有的停用父级会卡住该部门的其它编辑
                    $currentPid = $dept instanceof DeptModel ? (int) $dept->pid : 0;

                    if ((int) $parent->status !== 1 && $pid !== $currentPid) {
                        $fail('上级部门已停用，无法作为上级。');
                    }
                },
            ],
            'sort' => ['nullable', 'integer'],
            'status' => ['required', 'integer', 'in:0,1'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '部门名称',
            'pid' => '上级部门',
            'sort' => '排序',
            'status' => '状态',
            'remark' => '备注',
        ];
    }

    /**
     * 规范化后的落库字段。校验与写入共用同一套 pid 归一逻辑。
     */
    public function payload(): array
    {
        $data = $this->validated();

        return [
            'pid' => $this->pid(),
            'name' => $data['name'],
            'sort' => (int) ($data['sort'] ?? 0),
            'status' => (int) $data['status'],
            'remark' => $data['remark'] ?? null,
        ];
    }

    /**
     * 空值（含表单清空上级部门）一律归一到顶级；更新时缺省沿用原父级。
     */
    public function pid(): int
    {
        $value = $this->input('pid');

        if ($value === null || $value === '') {
            $dept = $this->route('dept');

            return $dept instanceof DeptModel ? (int) $dept->pid : 0;
        }

        return (int) $value;
    }
}
