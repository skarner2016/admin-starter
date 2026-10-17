<?php

namespace App\Http\Requests\System;

use App\Models\RoleModel;
use App\Services\AdminUserService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
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
                Rule::unique('admin_roles', 'name')->ignore($this->roleId()),
                // 只禁止「把别的角色改名成 super」；super 角色本身更新时必须能回传自己的名字，
                // 否则连它的备注/授权都改不了。停用与修改另有服务层守卫。
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($value !== AdminUserService::SUPER_ROLE) {
                        return;
                    }

                    $role = $this->route('role');

                    if (! $role instanceof RoleModel || $role->name !== AdminUserService::SUPER_ROLE) {
                        $fail('不允许将角色改名为 super。');
                    }
                },
            ],
            'status' => ['required', 'integer', 'in:0,1'],
            'remark' => ['nullable', 'string', 'max:255'],
            // permissions 是菜单树勾选出来的 menu id 数组
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:admin_menus,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '角色名称',
            'status' => '状态',
            'remark' => '备注',
            'permissions' => '权限',
        ];
    }

    private function roleId(): ?int
    {
        $role = $this->route('role');

        return $role instanceof RoleModel ? $role->id : null;
    }
}
