<?php

namespace App\Http\Requests\System;

use App\Models\RoleModel;
use App\Services\AdminUserService;
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
            'name' => ['required', 'string', 'max:64', Rule::notIn([AdminUserService::SUPER_ROLE]), Rule::unique('admin_roles', 'name')->ignore($this->roleId())],
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
