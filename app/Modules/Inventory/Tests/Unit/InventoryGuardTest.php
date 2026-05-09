<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Exceptions\InventoryDisabledException;
use App\Modules\Inventory\Models\StoreInventoryConfig;
use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Inventory\Support\InventoryGuard;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->sku = ItemSku::factory()->create(['tenant_id' => $this->tenant->id, 'item_id' => $this->item->id]);
});

test('全部 5 层启用时不抛', function () {
    InventoryGuard::assertEnabled($this->tenant->id, $this->store->id, $this->sku->id);
    expect(true)->toBeTrue();
});

test('tenant 关闭抛 InventoryDisabledException 且 layer=tenant', function () {
    TenantInventoryConfig::query()->where('tenant_id', $this->tenant->id)
        ->update(['inventory_enabled' => false]);

    try {
        InventoryGuard::assertEnabled($this->tenant->id, $this->store->id, $this->sku->id);
        $this->fail('expected exception');
    } catch (InventoryDisabledException $e) {
        expect($e->layer)->toBe('tenant');
    }
});

test('store 关闭抛异常且 layer=store', function () {
    StoreInventoryConfig::query()->where('store_id', $this->store->id)
        ->update(['inventory_enabled' => false]);

    try {
        InventoryGuard::assertEnabled($this->tenant->id, $this->store->id, $this->sku->id);
        $this->fail('expected exception');
    } catch (InventoryDisabledException $e) {
        expect($e->layer)->toBe('store');
    }
});

test('item 关闭抛异常且 layer=item', function () {
    $this->item->update(['inventory_enabled' => false]);

    try {
        InventoryGuard::assertEnabled($this->tenant->id, $this->store->id, $this->sku->id);
        $this->fail('expected exception');
    } catch (InventoryDisabledException $e) {
        expect($e->layer)->toBe('item');
    }
});

test('sku 关闭抛异常且 layer=sku', function () {
    $this->sku->update(['inventory_enabled' => false]);

    try {
        InventoryGuard::assertEnabled($this->tenant->id, $this->store->id, $this->sku->id);
        $this->fail('expected exception');
    } catch (InventoryDisabledException $e) {
        expect($e->layer)->toBe('sku');
    }
});

test('policy.track_type=NONE 抛异常且 layer=policy', function () {
    ProductInventoryPolicy::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)
        ->update(['inventory_track_type' => 'NONE']);

    try {
        InventoryGuard::assertEnabled($this->tenant->id, $this->store->id, $this->sku->id);
        $this->fail('expected exception');
    } catch (InventoryDisabledException $e) {
        expect($e->layer)->toBe('policy');
    }
});

test('negativeStockAllowed 两层 AND 语义', function () {
    expect(InventoryGuard::negativeStockAllowed($this->tenant->id, $this->sku->id))->toBeFalse();

    TenantInventoryConfig::query()->where('tenant_id', $this->tenant->id)
        ->update(['negative_stock_allowed' => true]);
    expect(InventoryGuard::negativeStockAllowed($this->tenant->id, $this->sku->id))->toBeFalse();

    ProductInventoryPolicy::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)
        ->update(['allow_negative_stock' => true]);
    expect(InventoryGuard::negativeStockAllowed($this->tenant->id, $this->sku->id))->toBeTrue();
});
