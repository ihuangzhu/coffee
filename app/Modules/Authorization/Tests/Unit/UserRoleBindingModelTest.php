<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(fn () => app(CurrentTenant::class)->set(null));

test('UserRoleBinding 工厂可创建 tenant 级绑定', function () {
    $b = UserRoleBinding::factory()->create();
    expect($b->id)->toHaveLength(26);
    expect($b->status)->toBe('active');
    expect($b->store_id)->toBeNull();
});

test('store 级绑定带 store_id', function () {
    $b = UserRoleBinding::factory()->storeScope()->create();
    expect($b->store_id)->not->toBeNull();
});

test('User.roleBindings 反向关系（跨租户全部 active）', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    UserRoleBinding::factory()->count(2)->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'tenant_id' => $tB->id]);

    // 跨租户：默认 BelongsToTenant 全局 scope 会过滤；显式 withoutGlobalScopes
    expect($u->roleBindings()->withoutGlobalScopes()->count())->toBe(3);
});

test('CurrentTenant scope 默认过滤当前租户绑定', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    UserRoleBinding::factory()->count(2)->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'tenant_id' => $tB->id]);

    app(CurrentTenant::class)->set($tA->id);
    expect(UserRoleBinding::query()->where('user_id', $u->id)->count())->toBe(2);
});

test('revoked 状态保留', function () {
    $b = UserRoleBinding::factory()->revoked()->create();
    expect($b->status)->toBe('revoked');
});
