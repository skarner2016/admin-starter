<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuModel extends Model
{
    public const TYPE_CATALOG = 'catalog';

    public const TYPE_MENU = 'menu';

    public const TYPE_EMBEDDED = 'embedded';

    public const TYPE_LINK = 'link';

    public const TYPE_BUTTON = 'button';

    /** 管理界面可选的全部类型 */
    public const TYPES = [
        self::TYPE_CATALOG,
        self::TYPE_MENU,
        self::TYPE_EMBEDDED,
        self::TYPE_LINK,
        self::TYPE_BUTTON,
    ];

    protected $table = 'admin_menus';

    protected $fillable = [
        'pid',
        'name',
        'path',
        'component',
        'redirect',
        'type',
        'auth_code',
        'icon',
        'sort',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'pid' => 'integer',
            'sort' => 'integer',
            'status' => 'integer',
            'meta' => 'array',
        ];
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'pid')->orderBy('sort');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            config('permission.models.role'),
            'admin_role_menu',
            'menu_id',
            'role_id',
        );
    }
}
