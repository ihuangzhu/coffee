<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Database\Factories\StockTxnFactory;
use App\Modules\Inventory\Enums\StockTxnBizType;
use App\Modules\Inventory\Enums\StockTxnDirection;
use App\Support\Eloquent\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 库存流水：append-only，不更新不删除。
 * 撤销 = 写一条反向记录，meta.cancels_txn_id 指向被撤销笔。
 */
class StockTxn extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const UPDATED_AT = null;
    public $timestamps = true; // 仅 created_at；UPDATED_AT 设 null 关闭 update

    protected $table = 'stock_txns';

    protected $guarded = [];

    protected $casts = [
        'biz_type' => StockTxnBizType::class,
        'direction' => StockTxnDirection::class,
        'qty_change' => 'decimal:4',
        'unit_cost_cents' => 'int',
        'amount_cents' => 'int',
        'occurred_at' => 'datetime',
        'meta_json' => 'array',
    ];

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ItemSku::class, 'sku_id');
    }

    protected static function newFactory(): StockTxnFactory
    {
        return StockTxnFactory::new();
    }
}
