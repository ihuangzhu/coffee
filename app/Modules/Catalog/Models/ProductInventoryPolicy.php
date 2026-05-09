<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductInventoryPolicyFactory;
use App\Modules\Catalog\Enums\InventoryTrackType;
use App\Modules\Catalog\Enums\StockDeductMode;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductInventoryPolicy extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'product_inventory_policies';
    protected $guarded = [];

    protected $casts = [
        'inventory_track_type' => InventoryTrackType::class,
        'stock_deduct_mode' => StockDeductMode::class,
        'allow_negative_stock' => 'bool',
        'batch_required' => 'bool',
        'expiry_required' => 'bool',
    ];

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ItemSku::class, 'sku_id');
    }

    protected static function newFactory(): ProductInventoryPolicyFactory
    {
        return ProductInventoryPolicyFactory::new();
    }
}
