<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    app(CurrentTenant::class)->set(null);
});

test('Store 工厂创建附带 tenant_id', function () {
    $s = Store::factory()->create();
    expect($s->tenant_id)->not->toBeNull();
    expect($s->tenant)->not->toBeNull();
});

test('CurrentTenant=null 时不过滤（登录前查询场景）', function () {
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    Store::factory()->create(['tenant_id' => $tA->id, 'name' => 'A 店']);
    Store::factory()->create(['tenant_id' => $tB->id, 'name' => 'B 店']);

    expect(Store::all())->toHaveCount(2);
});

test('BelongsToTenant 全局作用域只返回当前租户的 stores', function () {
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    Store::factory()->create(['tenant_id' => $tA->id, 'name' => 'A 店']);
    Store::factory()->create(['tenant_id' => $tB->id, 'name' => 'B 店']);

    app(CurrentTenant::class)->set($tA->id);

    $stores = Store::all();
    expect($stores)->toHaveCount(1);
    expect($stores->first()->name)->toBe('A 店');
});

test('BelongsToTenant 创建时自动注入 tenant_id', function () {
    $t = Tenant::factory()->create();
    app(CurrentTenant::class)->set($t->id);

    $s = Store::create(['name' => '示范店']);
    expect($s->tenant_id)->toBe($t->id);
});

test('CurrentTenant 未设置时创建抛 RuntimeException', function () {
    expect(fn () => Store::create(['name' => '应当失败店']))
        ->toThrow(RuntimeException::class);
});

test('withoutGlobalScopes 可绕过隔离', function () {
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    Store::factory()->create(['tenant_id' => $tA->id]);
    Store::factory()->create(['tenant_id' => $tB->id]);

    app(CurrentTenant::class)->set($tA->id);

    expect(Store::query()->withoutGlobalScopes()->count())->toBe(2);
});
