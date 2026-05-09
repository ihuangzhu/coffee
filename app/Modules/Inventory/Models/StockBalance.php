<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Database\Factories\StockBalanceFactory;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'stock_balances';

    protected $guarded = [];

    protected $casts = [
        'available_qty' => 'decimal:4',
        'reserved_qty' => 'decimal:4',
        'in_transit_qty' => 'decimal:4',
        'damaged_qty' => 'decimal:4',
        'version' => 'int',
    ];

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ItemSku::class, 'sku_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(StockOwner::class, 'stock_owner_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    protected static function newFactory(): StockBalanceFactory
    {
        return StockBalanceFactory::new();
    }
}
