<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemLoginLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'username' => $this->username,
            'success' => (bool) $this->success,
            // 原始原因码（locked / invalid_credentials / disabled），展示文案由前端翻译
            'reason' => $this->reason,
            'ip' => $this->ip,
            'userAgent' => $this->user_agent,
            'createTime' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
