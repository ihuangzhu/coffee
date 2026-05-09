<?php

declare(strict_types=1);

use App\Modules\Inventory\Models\StockTxn;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('stock_txns 主键自增 BIGINT', function () {
    $a = StockTxn::factory()->create();
    $b = StockTxn::factory()->create();

    expect($a->id)->toBeInt();
    expect($b->id)->toBeGreaterThan($a->id);
});

test('meta_json 自动 cast 为数组', function () {
    $txn = StockTxn::factory()->create(['meta_json' => ['subtype' => 'INITIAL', 'note' => 'hello']]);
    $reload = StockTxn::query()->find($txn->id);

    expect($reload->meta_json)->toBe(['subtype' => 'INITIAL', 'note' => 'hello']);
});

test('biz_type 枚举正确转换', function () {
    $txn = StockTxn::factory()->create(['biz_type' => 'STOCKTAKE_PROFIT', 'direction' => 'IN']);
    expect($txn->biz_type->value)->toBe('STOCKTAKE_PROFIT');
    expect($txn->direction->value)->toBe('IN');
});
