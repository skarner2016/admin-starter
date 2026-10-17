<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'remark' => $this->remark,
            'status' => (int) ($this->status ?? 1),
            'createTime' => $this->created_at?->format('Y-m-d H:i:s'),
            // permissions 是 menu id 数组（由 RoleService 预载，避免 N+1）
            'permissions' => $this->menu_ids ?? [],
        ];
    }
}
