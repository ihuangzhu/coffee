<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\TenantInventoryConfig;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->actor = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $this->actor->id, 'tenant_id' => $this->tenant->id, 'store_id' => null,
    ]);
    $this->actingAs($this->actor, 'web')
        ->withSession(['current_tenant_id' => $this->tenant->id]);
});

test('GET /tenant/settings/inventory 返回当前配置', function () {
    $this->get('/tenant/settings/inventory')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('tenant/Settings/InventoryConfig')
            ->where('config.inventory_enabled', true)
            ->where('config.inventory_cost_method', 'MOVING_AVG')
        );
});

test('PATCH /tenant/settings/inventory 更新成功', function () {
    $this->patch('/tenant/settings/inventory', [
        'inventory_enabled' => true,
        'multi_location_enabled' => true,
        'production_enabled' => false,
        'purchase_enabled' => false,
        'transfer_enabled' => false,
        'stocktaking_enabled' => true,
        'negative_stock_allowed' => true,
        'inventory_cost_method' => 'FIFO',
        'expiry_management_enabled' => false,
        'batch_management_enabled' => true,
        'auto_deduct_raw_material_enabled' => false,
    ])->assertRedirect();

    $cfg = TenantInventoryConfig::query()->where('tenant_id', $this->tenant->id)->first();
    expect($cfg->multi_location_enabled)->toBeTrue();
    expect($cfg->negative_stock_allowed)->toBeTrue();
    expect($cfg->batch_management_enabled)->toBeTrue();
    expect($cfg->inventory_cost_method->value)->toBe('FIFO');
});
