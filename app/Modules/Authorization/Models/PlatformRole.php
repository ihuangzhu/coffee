<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Models;

use App\Modules\Authorization\Database\Factories\PlatformRoleFactory;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 平台域角色（spec §3.2）。INV-14：与 Role 严格不互通。
 * 仅 users.is_platform_admin=true 的用户可持有 platform_role_id。
 */
class PlatformRole extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'platform_roles';

    protected $guarded = [];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'bool',
    ];

    protected static function newFactory(): PlatformRoleFactory
    {
        return PlatformRoleFactory::new();
    }
}
