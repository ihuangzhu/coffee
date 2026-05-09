<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Observers;

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;

/**
 * SKU 创建时自动建一条默认 policy（一对一）。
 * 默认值与 product_inventory_policies 迁移中的列默认一致：
 * track_type=FINISHED_GOOD, deduct_mode=MANUAL_DEDUCT。
 */
class ItemSkuObserver
{
    public function created(ItemSku $sku): void
    {
        ProductInventoryPolicy::query()->withoutGlobalScopes()->create([
            'tenant_id' => $sku->tenant_id,
            'sku_id' => $sku->id,
            'inventory_track_type' => 'FINISHED_GOOD',
            'stock_deduct_mode' => 'MANUAL_DEDUCT',
            'allow_negative_stock' => false,
            'batch_required' => false,
            'expiry_required' => false,
        ]);
    }
}
