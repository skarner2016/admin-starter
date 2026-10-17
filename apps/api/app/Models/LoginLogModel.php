<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLogModel extends Model
{
    protected $table = 'admin_login_logs';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'admin_user_id',
        'success',
        'reason',
        'ip',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
