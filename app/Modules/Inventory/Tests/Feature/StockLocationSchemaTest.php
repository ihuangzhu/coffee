<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Store;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Store 创建时自动建立默认 location（DEFAULT）', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);

    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $store->id)->first();

    $location = StockLocation::query()->withoutGlobalScopes()
        ->where('stock_owner_id', $owner->id)
        ->where('location_code', 'DEFAULT')
        ->first();

    expect($location)->not->toBeNull();
    expect($location->location_name)->toBe('默认库位');
    expect($location->location_type->value)->toBe('SHELF');
});

test('同 owner 下 location_code 唯一', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);
    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_ref_id', $store->id)->first();

    expect(fn () => StockLocation::factory()->create([
        'tenant_id' => $tenant->id,
        'stock_owner_id' => $owner->id,
        'location_code' => 'DEFAULT',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
