<?php

namespace App\Models;

use Spatie\Permission\Models\Role;

class RoleModel extends Role
{
    protected $table = 'admin_roles';

    protected function casts(): array
    {
        return [
            'status' => 'integer',
        ];
    }
}
