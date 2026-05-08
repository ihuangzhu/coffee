<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Models;

use App\Modules\Authorization\Database\Factories\UserRoleBindingFactory;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 用户-角色绑定（spec §3.3）。
 *
 * - 挂 BelongsToTenant：默认查询自动按 tenant_id 过滤
 * - 跨租户场景（如 me/permissions 收集所有租户绑定）必须显式 withoutGlobalScopes()
 * - status=revoked 留痕，权限解析时必须显式 status=active
 * - INV-F：scope=tenant 角色 ↔ store_id NULL；scope=store ↔ store_id 非 NULL（应用层校验）
 */
class UserRoleBinding extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'user_role_bindings';

    protected $guarded = [];

    protected $casts = [
        'granted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected static function newFactory(): UserRoleBindingFactory
    {
        return UserRoleBindingFactory::new();
    }
}
