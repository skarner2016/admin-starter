<?php

namespace App\Http\Requests\System;

use App\Models\AdminUserModel;
use App\Models\DeptModel;
use App\Services\AdminUserService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('post');

        return [
            'username' => ['required', 'string', 'max:64', Rule::unique('admin_users', 'username')->ignore($this->userId())],
            'realName' => ['required', 'string', 'max:64'],
            'avatar' => ['nullable', 'string', 'max:255'],
            'password' => $isCreate
                ? ['required', ...self::passwordRules()]
                : ['nullable', ...self::passwordRules()],
            'description' => ['nullable', 'string', 'max:255'],
            // 部门可空：super / 系统账号可能不属于任何部门；0 或空表示清空
            'deptId' => [
                'nullable',
                'integer',
                'min:0',
                function (string $attribute, mixed $value, Closure $fail) {
                    $deptId = (int) $value;

                    if ($deptId <= 0) {
                        return;
                    }

                    $dept = DeptModel::query()->find($deptId);

                    if (! $dept) {
                        $fail('部门不存在。');

                        return;
                    }

                    // 仅当部门发生变化时校验启用状态：否则挂在已停用部门下的存量用户连备注都改不了
                    $user = $this->route('user');
                    $current = $user instanceof AdminUserModel ? (int) $user->dept_id : 0;

                    if ((int) $dept->status !== 1 && $deptId !== $current) {
                        $fail('该部门已停用，无法分配。');
                    }
                },
            ],
            'status' => ['required', 'integer', 'in:0,1'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:admin_roles,name'],
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => '用户名',
            'realName' => '姓名',
            'password' => '密码',
            'deptId' => '部门',
            'status' => '状态',
            'roles' => '角色',
        ];
    }

    private function userId(): ?int
    {
        $user = $this->route('user');

        return $user instanceof AdminUserModel ? $user->id : null;
    }

    /**
     * @return array<int, string>
     */
    private static function passwordRules(): array
    {
        return [
            'string',
            'min:'.AdminUserService::PASSWORD_MIN_LENGTH,
            'max:64',
            'regex:'.AdminUserService::PASSWORD_PATTERN,
        ];
    }
}
