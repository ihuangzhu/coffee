<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\AdjustStockAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->actor = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $this->actor->id, 'tenant_id' => $this->tenant->id, 'store_id' => null,
    ]);
    $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->sku = ItemSku::factory()->create(['tenant_id' => $this->tenant->id, 'item_id' => $this->item->id]);
    $this->actingAs($this->actor, 'web')
        ->withSession(['current_tenant_id' => $this->tenant->id]);
});

test('POST /tenant/stock/adjust 调整成功 + 写流水', function () {
    $this->post('/tenant/stock/adjust', [
        'store_id' => $this->store->id,
        'sku_id' => $this->sku->id,
        'qty_change' => 100,
        'direction' => 'IN',
        'subtype' => 'INITIAL',
    ])->assertRedirect();

    expect(StockTxn::query()->where('tenant_id', $this->tenant->id)->count())->toBe(1);
    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(100.0);
});

test('POST /tenant/stock/stocktake 盘点写流水', function () {
    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $this->store->id)->first();
    $location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)->first();
    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $owner->id, $location->id,
        $this->sku->id, '50', 'IN', 'INITIAL', $this->actor->id,
    );

    $this->post('/tenant/stock/stocktake', [
        'store_id' => $this->store->id,
        'sku_id' => $this->sku->id,
        'actual_qty' => 60,
    ])->assertRedirect();

    $latest = StockTxn::query()->orderByDesc('id')->first();
    expect($latest->biz_type->value)->toBe('STOCKTAKE_PROFIT');
});

test('POST /tenant/stock/damage 报损成功', function () {
    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $this->store->id)->first();
    $location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)->first();
    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $owner->id, $location->id,
        $this->sku->id, '20', 'IN', 'INITIAL', $this->actor->id,
    );

    $this->post('/tenant/stock/damage', [
        'store_id' => $this->store->id,
        'sku_id' => $this->sku->id,
        'qty' => 5,
        'unit_cost_cents' => 800,
        'reason' => '过期',
    ])->assertRedirect();

    $latest = StockTxn::query()->orderByDesc('id')->first();
    expect($latest->biz_type->value)->toBe('DAMAGE_OUT');
    expect($latest->amount_cents)->toBe(4000);
});

test('POST /tenant/stock/txns/{id}/reverse 撤销', function () {
    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $this->store->id)->first();
    $location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)->first();
    $origId = AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $owner->id, $location->id,
        $this->sku->id, '10', 'IN', 'INITIAL', $this->actor->id,
    );

    $this->post("/tenant/stock/txns/{$origId}/reverse")->assertRedirect();

    expect(StockTxn::query()->count())->toBe(2);
    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(0.0);
});
