<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserOrgRel;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    app(CurrentTenant::class)->set(null);
});

test('user 关系返回正确 user', function () {
    $u = User::factory()->create();
    $rel = UserOrgRel::factory()->create(['user_id' => $u->id]);
    expect($rel->user->id)->toBe($u->id);
});

test('user.memberships 反向关系', function () {
    $u = User::factory()->create();
    UserOrgRel::factory()->count(2)->create(['user_id' => $u->id]);
    expect($u->memberships)->toHaveCount(2);
});

test('CurrentTenant 设置后默认 scope 仅返回当前租户的 rels', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    UserOrgRel::factory()->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);
    UserOrgRel::factory()->create(['user_id' => $u->id, 'tenant_id' => $tB->id]);

    app(CurrentTenant::class)->set($tA->id);
    expect(UserOrgRel::query()->where('user_id', $u->id)->count())->toBe(1);
});

test('withoutGlobalScopes 可跨租户列出 user 全部 memberships', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    UserOrgRel::factory()->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);
    UserOrgRel::factory()->create(['user_id' => $u->id, 'tenant_id' => $tB->id]);

    app(CurrentTenant::class)->set($tA->id);
    expect(
        UserOrgRel::query()->withoutGlobalScopes()->where('user_id', $u->id)->count()
    )->toBe(2);
});

test('store_id 可空表示租户级成员', function () {
    $rel = UserOrgRel::factory()->create(['store_id' => null]);
    expect($rel->store_id)->toBeNull();
});
