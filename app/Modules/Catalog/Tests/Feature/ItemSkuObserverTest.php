<?php
declare(strict_types=1);
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Catalog\Models\ProductInventoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('创建 ItemSku 时自动建立默认 policy', function () {
    $sku = ItemSku::factory()->create();

    $policy = ProductInventoryPolicy::query()->withoutGlobalScopes()
        ->where('sku_id', $sku->id)->first();

    expect($policy)->not->toBeNull();
    expect($policy->tenant_id)->toBe($sku->tenant_id);
    expect($policy->inventory_track_type->value)->toBe('FINISHED_GOOD');
    expect($policy->stock_deduct_mode->value)->toBe('MANUAL_DEDUCT');
    expect($policy->allow_negative_stock)->toBeFalse();
});

test('每个 sku 只能有一条 policy（DB 唯一约束）', function () {
    $sku = ItemSku::factory()->create(); // observer 已建一条

    expect(fn () => ProductInventoryPolicy::factory()->create([
        'tenant_id' => $sku->tenant_id,
        'sku_id' => $sku->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
