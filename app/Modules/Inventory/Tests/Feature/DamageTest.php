<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\AdjustStockAction;
use App\Modules\Inventory\Actions\DamageAction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Inventory\Models\StockTxn;
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

    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '20', 'IN', 'INITIAL', $this->user->id,
    );
});

test('报损 5 件：流水 DAMAGE_OUT direction=OUT qty_change=-5', function () {
    $txnId = DamageAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '5', 800, $this->user->id, '过期',
    );

    $txn = StockTxn::query()->find($txnId);
    expect($txn->biz_type->value)->toBe('DAMAGE_OUT');
    expect($txn->direction->value)->toBe('OUT');
    expect((float) $txn->qty_change)->toBe(-5.0);
    expect($txn->unit_cost_cents)->toBe(800);
    expect($txn->amount_cents)->toBe(4000);
    expect($txn->meta_json['reason'])->toBe('过期');

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(15.0);
    expect((float) $balance->damaged_qty)->toBe(0.0); // 第一期不写 damaged 桶
});

test('报损量超过 available：抛 BusinessException', function () {
    expect(fn () => DamageAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '999', 100, $this->user->id, null,
    ))->toThrow(BusinessException::class);
});

test('报损 qty <= 0 抛异常', function () {
    expect(fn () => DamageAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '0', 100, $this->user->id, null,
    ))->toThrow(BusinessException::class);
});
