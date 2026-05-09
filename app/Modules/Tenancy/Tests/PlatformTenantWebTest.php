<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $admin = User::factory()->platformAdmin()->create();
    $this->actingAs($admin, 'web');
});

test('GET /platform/tenants 返回 Inertia 页面与租户分页数据', function () {
    Tenant::factory()->create(['name' => 'A咖啡']);
    Tenant::factory()->create(['name' => 'B咖啡']);

    $this->get('/platform/tenants')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('platform/Tenants/Index')
            ->where('total', 2)
            ->where('page', 1)
            ->has('rows', 2)
        );
});

test('GET /platform/tenants?q=A&status=active 过滤生效', function () {
    Tenant::factory()->create(['name' => 'A咖啡', 'status' => 'active']);
    Tenant::factory()->create(['name' => 'B咖啡', 'status' => 'active']);
    Tenant::factory()->create(['name' => 'A停业', 'status' => 'disabled']);

    $this->get('/platform/tenants?q=A&status=active')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('total', 1)->has('rows', 1));
});

test('POST /platform/tenants 同时建 Tenant + User + Membership', function () {
    $resp = $this->post('/platform/tenants', [
        'tenant_name' => '九号咖啡',
        'owner_name' => '老王',
        'owner_phone' => '13900000099',
        'owner_password' => 'secret123',
    ]);

    $resp->assertRedirect('/platform/tenants');

    $tenant = Tenant::query()->where('name', '九号咖啡')->firstOrFail();
    $user = User::query()->where('phone', '13900000099')->firstOrFail();
    expect($user->name)->toBe('老王');
    expect(Membership::query()->withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->exists())->toBeTrue();
});

test('POST /platform/tenants 已存在 phone 复用 user 不重置密码', function () {
    $existing = User::factory()->create(['phone' => '13900000088', 'name' => '原名']);
    $oldHash = $existing->password;

    $this->post('/platform/tenants', [
        'tenant_name' => '复用咖啡',
        'owner_name' => '新名',
        'owner_phone' => '13900000088',
        'owner_password' => 'secret123',
    ])->assertRedirect('/platform/tenants');

    $existing->refresh();
    expect($existing->name)->toBe('原名');
    expect($existing->password)->toBe($oldHash);
    expect(Membership::query()->withoutGlobalScopes()
        ->where('user_id', $existing->id)->exists())->toBeTrue();
});

test('PATCH /platform/tenants/{id} 更新名称与状态', function () {
    $t = Tenant::factory()->create(['name' => '旧名', 'status' => 'active']);

    $this->patch("/platform/tenants/{$t->id}", [
        'name' => '新名',
        'status' => 'disabled',
    ])->assertRedirect();

    $t->refresh();
    expect($t->name)->toBe('新名');
    expect($t->status->value)->toBe('disabled');
});
