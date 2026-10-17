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
