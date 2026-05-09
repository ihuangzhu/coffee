<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('stock_balances 行能正常创建并查询', function () {
    $balance = StockBalance::factory()->create(['available_qty' => 100]);

    expect((float) $balance->available_qty)->toBe(100.0);
    expect((float) $balance->reserved_qty)->toBe(0.0);
    expect($balance->version)->toBe(0);
});

test('唯一键约束（同 tenant+owner+location+sku 不能重复）', function () {
    $tenant = Tenant::factory()->create();
    $owner = StockOwner::factory()->create(['tenant_id' => $tenant->id]);
    $loc = StockLocation::factory()->create(['tenant_id' => $tenant->id, 'stock_owner_id' => $owner->id]);
    $sku = ItemSku::factory()->create(['tenant_id' => $tenant->id]);

    StockBalance::factory()->create([
        'tenant_id' => $tenant->id, 'stock_owner_id' => $owner->id,
        'location_id' => $loc->id, 'sku_id' => $sku->id,
    ]);

    expect(fn () => StockBalance::factory()->create([
        'tenant_id' => $tenant->id, 'stock_owner_id' => $owner->id,
        'location_id' => $loc->id, 'sku_id' => $sku->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
