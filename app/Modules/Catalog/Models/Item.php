<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ItemFactory;
use App\Modules\Catalog\Enums\ItemStatus;
use App\Modules\Catalog\Enums\ItemType;
use App\Modules\Catalog\Enums\OwnerType;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 物料主数据。所有可销售/采购/原料/服务实体的统一抽象。
 * SKU、库存、BOM 等子系统均挂在此 item。
 */
class Item extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'items';
    protected $guarded = [];

    protected $casts = [
        'owner_type' => OwnerType::class,
        'item_type' => ItemType::class,
        'status' => ItemStatus::class,
        'sku_enabled' => 'bool',
        'inventory_enabled' => 'bool',
    ];

    public function businessCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'business_category_id');
    }

    public function inventoryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'inventory_category_id');
    }

    public function skus(): HasMany
    {
        return $this->hasMany(ItemSku::class);
    }

    protected static function newFactory(): ItemFactory
    {
        return ItemFactory::new();
    }
}
