<?php

declare(strict_types=1);

use App\Modules\Inventory\Models\StockQuant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('stock_quants 行可创建（含批次/保质期）', function () {
    $quant = StockQuant::factory()->create([
        'batch_no' => 'B20260508',
        'expiry_date' => '2026-12-31',
        'qty' => 50,
        'unit_cost_cents' => 1234,
    ]);

    expect($quant->batch_no)->toBe('B20260508');
    expect($quant->expiry_date->format('Y-m-d'))->toBe('2026-12-31');
    expect((float) $quant->qty)->toBe(50.0);
    expect($quant->unit_cost_cents)->toBe(1234);
});

test('batch_no 可为空（第一期场景）', function () {
    $quant = StockQuant::factory()->create(['batch_no' => null, 'expiry_date' => null]);
    expect($quant->batch_no)->toBeNull();
    expect($quant->expiry_date)->toBeNull();
});
