<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Support;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Inventory\Exceptions\InventoryDisabledException;
use App\Modules\Inventory\Models\StoreInventoryConfig;
use App\Modules\Inventory\Models\TenantInventoryConfig;

/**
 * 五层 toggle 校验：tenant → store → sku → item → policy.track_type != NONE。
 * 任一层 false / 不存在均抛 InventoryDisabledException。
 *
 * 使用示例：
 *   InventoryGuard::assertEnabled($tenantId, $storeId, $skuId);
 *   // 若任意层关闭，抛 403 业务异常
 */
class InventoryGuard
{
    public static function assertEnabled(string $tenantId, string $storeId, string $skuId): void
    {
        $tenantCfg = TenantInventoryConfig::query()->where('tenant_id', $tenantId)->first();
        if (! $tenantCfg || ! $tenantCfg->inventory_enabled) {
            throw new InventoryDisabledException('tenant', $tenantId);
        }

        $storeCfg = StoreInventoryConfig::query()->where('store_id', $storeId)->first();
        if (! $storeCfg || ! $storeCfg->inventory_enabled) {
            throw new InventoryDisabledException('store', $storeId);
        }

        $sku = ItemSku::query()->withoutGlobalScopes()->whereKey($skuId)->first();
        if (! $sku || ! $sku->inventory_enabled) {
            throw new InventoryDisabledException('sku', $skuId);
        }

        $item = Item::query()->withoutGlobalScopes()->whereKey($sku->item_id)->first();
        if (! $item || ! $item->inventory_enabled) {
            throw new InventoryDisabledException('item', $sku->item_id);
        }

        $policy = ProductInventoryPolicy::query()->withoutGlobalScopes()->where('sku_id', $skuId)->first();
        if (! $policy || $policy->inventory_track_type->value === 'NONE') {
            throw new InventoryDisabledException('policy', $skuId);
        }
    }

    /**
     * 校验"允许负库存"两层 AND（tenant.negative_stock_allowed AND policy.allow_negative_stock）。
     */
    public static function negativeStockAllowed(string $tenantId, string $skuId): bool
    {
        $tenant = TenantInventoryConfig::query()->where('tenant_id', $tenantId)->first();
        if (! $tenant || ! $tenant->negative_stock_allowed) {
            return false;
        }
        $policy = ProductInventoryPolicy::query()->withoutGlobalScopes()->where('sku_id', $skuId)->first();

        return (bool) ($policy?->allow_negative_stock);
    }
}
