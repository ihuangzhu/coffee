<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ItemSkuFactory;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemSku extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'item_skus';
    protected $guarded = [];

    protected $casts = [
        'spec_json' => 'array',
        'sale_price_cents' => 'int',
        'cost_price_cents' => 'int',
        'inventory_enabled' => 'bool',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function policy(): HasOne
    {
        return $this->hasOne(ProductInventoryPolicy::class, 'sku_id');
    }

    protected static function newFactory(): ItemSkuFactory
    {
        return ItemSkuFactory::new();
    }
}
