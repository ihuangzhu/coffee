<?php

declare(strict_types=1);

use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Tenant 创建时自动建立 tenant_inventory_configs 行', function () {
    $tenant = Tenant::factory()->create();

    $cfg = TenantInventoryConfig::query()->where('tenant_id', $tenant->id)->first();

    expect($cfg)->not->toBeNull();
    expect($cfg->inventory_enabled)->toBeTrue();
    expect($cfg->stocktaking_enabled)->toBeTrue();
    expect($cfg->batch_management_enabled)->toBeFalse();
    expect($cfg->inventory_cost_method->value)->toBe('MOVING_AVG');
});

test('tenant_id 唯一约束（不能重复建）', function () {
    $tenant = Tenant::factory()->create();

    expect(fn () => TenantInventoryConfig::factory()->create(['tenant_id' => $tenant->id]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
