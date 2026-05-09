<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\AdjustStockAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Exceptions\BusinessException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $this->store->id)->first();
    $this->location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $this->owner->id)->first();
    $this->item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->sku = ItemSku::factory()->create(['tenant_id' => $this->tenant->id, 'item_id' => $this->item->id]);
    $this->user = User::factory()->create();
});

test('INITIAL_STOCK 写入：从 0 加到 100 + 流水 + balance', function () {
    $txnId = AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '100', 'IN', 'INITIAL', $this->user->id,
    );

    $txn = StockTxn::query()->find($txnId);
    expect($txn->biz_type->value)->toBe('ADJUSTMENT');
    expect($txn->direction->value)->toBe('IN');
    expect((float) $txn->qty_change)->toBe(100.0);
    expect($txn->meta_json['subtype'])->toBe('INITIAL');

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(100.0);
    expect($balance->version)->toBe(1);
});

test('MANUAL OUT 扣减库存', function () {
    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '50', 'IN', 'INITIAL', $this->user->id,
    );

    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '-20', 'OUT', 'MANUAL', $this->user->id,
    );

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(30.0);
    expect($balance->version)->toBe(2);
});

test('库存不足且未开启负库存：抛 BusinessException', function () {
    expect(fn () => AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '-10', 'OUT', 'MANUAL', $this->user->id,
    ))->toThrow(BusinessException::class);
});

test('开启负库存（两层 AND）后允许扣到负数', function () {
    TenantInventoryConfig::query()->where('tenant_id', $this->tenant->id)
        ->update(['negative_stock_allowed' => true]);
    ProductInventoryPolicy::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)
        ->update(['allow_negative_stock' => true]);

    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '-10', 'OUT', 'MANUAL', $this->user->id,
    );

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(-10.0);
});

test('tenant 关闭库存时调用直接抛 InventoryDisabledException', function () {
    TenantInventoryConfig::query()->where('tenant_id', $this->tenant->id)
        ->update(['inventory_enabled' => false]);

    expect(fn () => AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '10', 'IN', 'INITIAL', $this->user->id,
    ))->toThrow(\App\Modules\Inventory\Exceptions\InventoryDisabledException::class);
});
