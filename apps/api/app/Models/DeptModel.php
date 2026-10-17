<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeptModel extends Model
{
    protected $table = 'admin_depts';

    protected $fillable = [
        'pid',
        'name',
        'sort',
        'status',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'pid' => 'integer',
            'sort' => 'integer',
            'status' => 'integer',
        ];
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'pid')->orderBy('sort');
    }
}
