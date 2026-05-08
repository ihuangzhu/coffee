<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Database\Factories\StoreFactory;
use App\Modules\Tenancy\Enums\StoreStatus;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 门店实体，归属于租户。挂 BelongsToTenant trait 实现自动隔离与 tenant_id 注入。
 */
class Store extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'stores';

    protected $guarded = [];

    protected $casts = ['status' => StoreStatus::class];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected static function newFactory(): StoreFactory
    {
        return StoreFactory::new();
    }
}
