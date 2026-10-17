<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class AdminUserModel extends Authenticatable
{
    use HasApiTokens;
    use HasRoles;

    protected $table = 'admin_users';

    protected $fillable = [
        'username',
        'real_name',
        'avatar',
        'password',
        'description',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'integer',
        ];
    }
}
