<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken;

class PersonalAccessTokenModel extends PersonalAccessToken
{
    protected $table = 'admin_personal_access_tokens';

    /**
     * 在 Sanctum 默认字段基础上放开 session_id（登录会话标识）。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'session_id',
    ];
}
