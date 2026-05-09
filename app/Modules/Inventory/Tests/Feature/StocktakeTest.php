<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\AdjustStockAction;
use App\Modules\Inventory\Actions\StocktakeAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Tenancy\Models\Tenant;
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

    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '50', 'IN', 'INITIAL', $this->user->id,
    );
});

test('盘盈：实盘 60 vs 账面 50 → STOCKTAKE_PROFIT direction=IN', function () {
    $txnId = StocktakeAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '60', $this->user->id, '盘盈测试',
    );

    $txn = StockTxn::query()->find($txnId);
    expect($txn->biz_type->value)->toBe('STOCKTAKE_PROFIT');
    expect($txn->direction->value)->toBe('IN');
    expect((float) $txn->qty_change)->toBe(10.0);
    expect($txn->meta_json['book_qty'])->toBe('50.0000');
    expect($txn->meta_json['actual_qty'])->toBe('60');

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(60.0);
});

test('盘亏：实盘 30 vs 账面 50 → STOCKTAKE_LOSS direction=OUT', function () {
    $txnId = StocktakeAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '30', $this->user->id, null,
    );

    $txn = StockTxn::query()->find($txnId);
    expect($txn->biz_type->value)->toBe('STOCKTAKE_LOSS');
    expect($txn->direction->value)->toBe('OUT');
    expect((float) $txn->qty_change)->toBe(-20.0);

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(30.0);
});

test('实盘等于账面：返回 null 不写流水', function () {
    $beforeCount = StockTxn::query()->count();

    $result = StocktakeAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '50', $this->user->id, null,
    );

    expect($result)->toBeNull();
    expect(StockTxn::query()->count())->toBe($beforeCount);
});
