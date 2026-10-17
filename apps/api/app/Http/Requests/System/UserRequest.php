<?php

namespace App\Http\Requests\System;

use App\Models\AdminUserModel;
use App\Models\DeptModel;
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
                ? ['required', 'string', 'min:8', 'max:64', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/']
                : ['nullable', 'string', 'min:8', 'max:64', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'],
            'description' => ['nullable', 'string', 'max:255'],
            // 部门可空：super / 系统账号可能不属于任何部门；0 或空表示清空
            'deptId' => [
                'nullable',
                'integer',
                'min:0',
                function (string $attribute, mixed $value, Closure $fail) {
                    if ((int) $value > 0 && ! DeptModel::query()->whereKey($value)->exists()) {
                        $fail('部门不存在。');
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
}
