<?php

declare(strict_types=1);

use App\Modules\Inventory\Models\StockOwner;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Store 创建时自动建立 stock_owners 行（owner_type=STORE）', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);

    $owner = StockOwner::query()->withoutGlobalScopes()
        ->where('owner_type', 'STORE')
        ->where('owner_ref_id', $store->id)
        ->first();

    expect($owner)->not->toBeNull();
    expect($owner->tenant_id)->toBe($tenant->id);
    expect($owner->name)->toContain('主仓');
});

test('同 store 不能重复建 stock_owner', function () {
    $tenant = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $tenant->id]);

    expect(fn () => StockOwner::factory()->create([
        'tenant_id' => $tenant->id,
        'owner_type' => 'STORE',
        'owner_ref_id' => $store->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
