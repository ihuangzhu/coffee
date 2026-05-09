<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\AdjustStockAction;
use App\Modules\Inventory\Actions\DamageAction;
use App\Modules\Inventory\Actions\ReverseStockTxnAction;
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
});

test('撤销 ADJUSTMENT IN 笔：库存回到撤销前', function () {
    $orig = AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '50', 'IN', 'INITIAL', $this->user->id,
    );

    $reverseId = ReverseStockTxnAction::handle($orig, $this->user->id);

    $reverseTxn = StockTxn::query()->find($reverseId);
    expect($reverseTxn->biz_type->value)->toBe('ADJUSTMENT');
    expect($reverseTxn->direction->value)->toBe('OUT');
    expect((float) $reverseTxn->qty_change)->toBe(-50.0);
    expect($reverseTxn->meta_json['cancels_txn_id'])->toBe($orig);

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(0.0);
});

test('撤销 DAMAGE_OUT：库存恢复', function () {
    AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '20', 'IN', 'INITIAL', $this->user->id,
    );
    $damageTxn = DamageAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '5', 800, $this->user->id, '过期',
    );

    ReverseStockTxnAction::handle($damageTxn, $this->user->id);

    $balance = StockBalance::query()->withoutGlobalScopes()
        ->where('sku_id', $this->sku->id)->first();
    expect((float) $balance->available_qty)->toBe(20.0);
});

test('已撤销的不能再撤', function () {
    $orig = AdjustStockAction::handle(
        $this->tenant->id, $this->store->id, $this->owner->id, $this->location->id,
        $this->sku->id, '10', 'IN', 'INITIAL', $this->user->id,
    );

    ReverseStockTxnAction::handle($orig, $this->user->id);

    expect(fn () => ReverseStockTxnAction::handle($orig, $this->user->id))
        ->toThrow(BusinessException::class);
});

test('不存在的 txn_id 抛异常', function () {
    expect(fn () => ReverseStockTxnAction::handle(99999, $this->user->id))
        ->toThrow(BusinessException::class);
});
