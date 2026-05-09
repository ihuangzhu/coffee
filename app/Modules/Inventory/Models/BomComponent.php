<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Database\Factories\BomComponentFactory;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomComponent extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'bom_components';

    protected $guarded = [];

    protected $casts = [
        'consume_qty' => 'decimal:4',
        'loss_rate' => 'decimal:4',
        'sequence_no' => 'int',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function componentSku(): BelongsTo
    {
        return $this->belongsTo(ItemSku::class, 'component_sku_id');
    }

    protected static function newFactory(): BomComponentFactory
    {
        return BomComponentFactory::new();
    }
}
