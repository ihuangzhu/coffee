<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Database\Factories\TenantFactory;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 租户实体（顶层组织单位）。所有业务数据通过 tenant_id 间接归属此模型。
 * 注意：本模型不使用 BelongsToTenant，因其本身就是租户根。
 */
class Tenant extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'tenants';

    protected $guarded = [];

    protected $casts = ['status' => TenantStatus::class];

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
