<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Models;

use App\Modules\Authorization\Database\Factories\RoleFactory;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 商户域角色（spec §3.1）。
 *
 * tenant_id NULL = 系统预置全局模板；非 NULL = 商户专属。
 * **不挂 BelongsToTenant trait** —— 全局模板需在 tenant_id IS NULL 下跨租户可见，
 * 由 Service 层显式做 (tenant_id = X OR tenant_id IS NULL) 过滤。
 */
class Role extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'roles';

    protected $guarded = [];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'bool',
    ];

    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }
}
