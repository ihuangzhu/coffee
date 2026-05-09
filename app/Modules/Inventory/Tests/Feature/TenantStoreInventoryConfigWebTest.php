<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StoreInventoryConfig;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->actor = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $this->actor->id, 'tenant_id' => $this->tenant->id, 'store_id' => null,
    ]);
    $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->actor, 'web')
        ->withSession(['current_tenant_id' => $this->tenant->id]);
});

test('GET /tenant/stores/{id}/inventory 返回门店配置', function () {
    $this->get("/tenant/stores/{$this->store->id}/inventory")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('tenant/Stores/InventoryConfig')
            ->where('store.id', $this->store->id)
            ->where('config.inventory_enabled', true)
        );
});

test('PATCH 更新成功', function () {
    $this->patch("/tenant/stores/{$this->store->id}/inventory", [
        'inventory_enabled' => false,
        'multi_location_enabled' => true,
        'default_stock_mode' => 'FULL',
        'production_enabled' => true,
        'allow_direct_stock_adjustment' => false,
    ])->assertRedirect();

    $cfg = StoreInventoryConfig::query()->where('store_id', $this->store->id)->first();
    expect($cfg->inventory_enabled)->toBeFalse();
    expect($cfg->default_stock_mode)->toBe('FULL');
    expect($cfg->production_enabled)->toBeTrue();
});

test('跨租户 store_id 404', function () {
    $other = Tenant::factory()->create();
    $alien = Store::factory()->create(['tenant_id' => $other->id]);

    $this->get("/tenant/stores/{$alien->id}/inventory")->assertNotFound();
});
