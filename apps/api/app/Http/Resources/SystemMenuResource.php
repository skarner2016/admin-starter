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
            // 排序的权威来源是 admin_menus.sort；运行时 menu.all 会把它映射到 meta.order
            'sort' => (int) $this->sort,
            'meta' => $this->meta ?? [],
        ];
    }
}
