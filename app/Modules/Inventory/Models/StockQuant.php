<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Database\Factories\StockQuantFactory;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockQuant extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'stock_quants';

    protected $guarded = [];

    protected $casts = [
        'expiry_date' => 'date',
        'qty' => 'decimal:4',
        'unit_cost_cents' => 'int',
    ];

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ItemSku::class, 'sku_id');
    }

    protected static function newFactory(): StockQuantFactory
    {
        return StockQuantFactory::new();
    }
}
