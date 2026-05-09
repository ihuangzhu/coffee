<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $admin = User::factory()->platformAdmin()->create();
    $this->actingAs($admin, 'web');
    $this->tenant = Tenant::factory()->create(['name' => 'A咖啡']);
});

test('GET /platform/tenants/{id}/stores 列出该租户的门店', function () {
    Store::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '徐汇店']);
    Store::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '朝阳店']);
    $other = Tenant::factory()->create();
    Store::factory()->create(['tenant_id' => $other->id, 'name' => '别家店']);

    $this->get("/platform/tenants/{$this->tenant->id}/stores")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/Stores/Index')
            ->where('tenant.id', $this->tenant->id)
            ->where('total', 2)
            ->has('rows', 2)
        );
});

test('GET /platform/tenants/{id}/stores 支持 q + status 过滤', function () {
    Store::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '徐汇店', 'status' => 'active']);
    Store::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '朝阳店', 'status' => 'disabled']);
    Store::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '徐汇旗舰', 'status' => 'active']);

    $this->get("/platform/tenants/{$this->tenant->id}/stores?q=徐汇&status=active")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('total', 2));
});

test('POST /platform/tenants/{id}/stores 新建门店', function () {
    $this->post("/platform/tenants/{$this->tenant->id}/stores", ['name' => '新店'])
        ->assertRedirect("/platform/tenants/{$this->tenant->id}/stores");

    expect(Store::query()->withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('name', '新店')
        ->where('status', 'active')
        ->exists())->toBeTrue();
});

test('PATCH /platform/tenants/{id}/stores/{sid} 更新名称与状态', function () {
    $store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '旧店', 'status' => 'active']);

    $this->patch("/platform/tenants/{$this->tenant->id}/stores/{$store->id}", [
        'name' => '新店',
        'status' => 'disabled',
    ])->assertRedirect();

    $store->refresh();
    expect($store->name)->toBe('新店');
    expect($store->status->value)->toBe('disabled');
});

test('GET /platform/tenants/{id}/stores/{sid}/edit 跨租户访问 404', function () {
    $other = Tenant::factory()->create();
    $store = Store::factory()->create(['tenant_id' => $other->id, 'name' => '别家店']);

    $this->get("/platform/tenants/{$this->tenant->id}/stores/{$store->id}/edit")
        ->assertNotFound();
});
