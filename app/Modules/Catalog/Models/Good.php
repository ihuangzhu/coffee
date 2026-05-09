<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\GoodFactory;
use App\Modules\Catalog\Enums\GoodStatus;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 商品。租户级数据，挂 BelongsToTenant 自动隔离。
 */
class Good extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'goods';

    protected $guarded = [];

    protected $casts = [
        'status' => GoodStatus::class,
        'sku_base_price_cents' => 'int',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected static function newFactory(): GoodFactory
    {
        return GoodFactory::new();
    }
}
