<?php

namespace App\Http\Requests\System;

use App\Models\MenuModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->menuId();

        return [
            'name' => ['required', 'string', 'max:64', Rule::unique('admin_menus', 'name')->ignore($id)],
            // path 不做唯一约束：子级用相对片段（如 workspace），跨父级可以重名；按钮类无路径
            'path' => ['nullable', 'string', 'max:191'],
            'pid' => ['nullable', 'integer', 'min:0'],
            'component' => ['nullable', 'string', 'max:191'],
            'redirect' => ['nullable', 'string', 'max:191'],
            'type' => ['required', 'string', Rule::in(MenuModel::TYPES)],
            'authCode' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'integer', 'in:0,1'],
            'meta' => ['nullable', 'array'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '菜单名称',
            'path' => '路由地址',
            'type' => '菜单类型',
            'status' => '状态',
            'authCode' => '权限标识',
        ];
    }

    private function menuId(): ?int
    {
        $menu = $this->route('menu');

        return $menu instanceof MenuModel ? $menu->id : null;
    }
}
