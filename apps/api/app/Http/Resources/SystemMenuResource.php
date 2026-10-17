<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 菜单管理接口出参（/system/menu/list 等）。
 * 与运行时菜单（MenuService::menusForUser）不同：这里返回全部类型与状态。
 */
class SystemMenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'pid' => (string) $this->pid,
            'name' => $this->name,
            'path' => $this->path,
            'component' => $this->component,
            'type' => $this->type,
            'authCode' => $this->auth_code,
            'redirect' => $this->redirect,
            'status' => (int) $this->status,
            'meta' => $this->meta ?? [],
        ];
    }
}
