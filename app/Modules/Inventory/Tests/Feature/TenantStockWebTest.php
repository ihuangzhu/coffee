<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\AdjustStockAction;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
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
    $this->actingAs($this->actor, 'web')
        ->withSession(['current_tenant_id' => $this->tenant->id]);
});

test('GET /tenant/stock 未选门店时返回空 rows', function () {
    $this->get('/tenant/stock')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('tenant/Stock/Index')
            ->where('rows', [])
            ->where('total', 0)
        );
});

test('GET /tenant/stock 已选门店时返回该门店库存', function () {
    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $this->store->id)->first();
    $location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)->first();

    $item = Item::factory()->create(['tenant_id' => $this->tenant->id, 'item_name' => '咖啡豆']);
    $sku = ItemSku::factory()->create(['tenant_id' => $this->tenant->id, 'item_id' => $item->id]);

    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $owner->id, $location->id,
        $sku->id, '100', 'IN', 'INITIAL', $this->actor->id,
    );

    $this->withSession([
        'current_tenant_id' => $this->tenant->id,
        'current_store_id' => $this->store->id,
    ])->get('/tenant/stock')
        ->assertInertia(fn ($p) => $p
            ->where('total', 1)
            ->where('rows.0.item_name', '咖啡豆')
            ->where('rows.0.available_qty', 100)
        );
});

test('GET /tenant/stock/txns 列出本租户全部流水', function () {
    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $this->store->id)->first();
    $location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)->first();
    $item = Item::factory()->create(['tenant_id' => $this->tenant->id]);
    $sku = ItemSku::factory()->create(['tenant_id' => $this->tenant->id, 'item_id' => $item->id]);

    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $owner->id, $location->id,
        $sku->id, '50', 'IN', 'INITIAL', $this->actor->id,
    );

    $this->get('/tenant/stock/txns')
        ->assertInertia(fn ($p) => $p
            ->component('tenant/Stock/Txns')
            ->where('total', 1)
            ->where('rows.0.biz_type', 'ADJUSTMENT')
            ->where('rows.0.qty_change', 50)
        );
});

test('跨租户流水不可见', function () {
    $other = Tenant::factory()->create();
    $store2 = Store::factory()->create(['tenant_id' => $other->id]);
    $owner2 = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $store2->id)->first();
    $location2 = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner2->id)->first();
    $item2 = Item::factory()->create(['tenant_id' => $other->id]);
    $sku2 = ItemSku::factory()->create(['tenant_id' => $other->id, 'item_id' => $item2->id]);

    $u2 = User::factory()->create();
    AdjustStockAction::handle(
        $other->id, $store2->id, $owner2->id, $location2->id,
        $sku2->id, '99', 'IN', 'INITIAL', $u2->id,
    );

    $this->get('/tenant/stock/txns')
        ->assertInertia(fn ($p) => $p->where('total', 0));
});
