<?php

declare(strict_types=1);

use App\Modules\Inventory\Models\StoreInventoryConfig;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Store 创建时自动建立 store_inventory_configs 行', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);

    $cfg = StoreInventoryConfig::query()->where('store_id', $store->id)->first();

    expect($cfg)->not->toBeNull();
    expect($cfg->tenant_id)->toBe($tenant->id);
    expect($cfg->inventory_enabled)->toBeTrue();
    expect($cfg->allow_direct_stock_adjustment)->toBeTrue();
    expect($cfg->default_stock_mode)->toBe('SIMPLE');
});

test('store_id 唯一约束', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);

    expect(fn () => StoreInventoryConfig::factory()->create([
        'tenant_id' => $tenant->id, 'store_id' => $store->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
