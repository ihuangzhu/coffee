<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\MembershipFactory;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 用户与租户/门店的成员关系（base.md §6.1 membership）。
 *
 * - store_id NULL 表示租户级成员（如商户管理员）
 * - store_id 非 NULL 表示门店级成员（如店长/店员）
 * - status=left 时保留历史记录用于审计追溯，活跃查询必须显式过滤 status=active
 *
 * 挂 BelongsToTenant 让默认查询安全；跨租户查询场景必须 withoutGlobalScopes()。
 */
class Membership extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'memberships';

    protected $guarded = [];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected static function newFactory(): MembershipFactory
    {
        return MembershipFactory::new();
    }
}
