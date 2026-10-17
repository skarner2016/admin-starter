<?php

namespace App\Http\Resources;

use App\Services\AdminUserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'userId' => (string) $this->id,
            'username' => $this->username,
            'realName' => $this->real_name,
            'avatar' => $this->avatar ?? '',
            'roles' => $this->getRoleNames()->all(),
            // 所有用户统一返回同一个首页地址
            'homePath' => AdminUserService::HOME_PATH,
            'description' => $this->description,
        ];
    }
}
