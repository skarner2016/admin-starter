<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Permission;
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

    /**
     * spatie 默认按「用户拥有的角色」判权，不看角色 status，会让停用角色
     * 依旧通过 permission 中间件拿到写权限。这里收窄为只认启用角色，
     * 与 AdminUserService::permissionCodesFor() / MenuService::menusForUser() 口径一致。
     */
    protected function hasPermissionViaRole(Permission $permission): bool
    {
        return $this->roles()
            ->where('status', 1)
            ->whereHas('permissions', fn ($query) => $query->whereKey($permission->getKey()))
            ->exists();
    }
}
