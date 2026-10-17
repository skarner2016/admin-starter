<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'dept_id',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'dept_id' => 'integer',
            'status' => 'integer',
        ];
    }

    public function dept(): BelongsTo
    {
        return $this->belongsTo(DeptModel::class, 'dept_id');
    }
}
