<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'username' => $this->username,
            'realName' => $this->real_name,
            'avatar' => $this->avatar ?? '',
            'description' => $this->description,
            // id 一律字符串：前端部门树选择器按字符串 id 绑定
            'deptId' => $this->dept_id === null ? '' : (string) $this->dept_id,
            // 不用 whenLoaded：关系已加载但值为 null（未分配部门）时它返回 null，而非默认值
            'deptName' => $this->dept?->name ?? '',
            'status' => (int) $this->status,
            'roles' => $this->whenLoaded(
                'roles',
                fn () => $this->roles->pluck('name')->values()->all(),
                fn () => $this->getRoleNames()->all(),
            ),
            'createTime' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
