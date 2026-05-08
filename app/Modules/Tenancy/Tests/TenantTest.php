<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Tenant 创建生成 ULID 主键', function () {
    $t = Tenant::factory()->create(['name' => '九号咖啡']);
    expect($t->id)->toBeString()->toHaveLength(26);
    expect($t->name)->toBe('九号咖啡');
});

test('Tenant status 转换为 enum', function () {
    $t = Tenant::factory()->create();
    expect($t->status)->toBeInstanceOf(TenantStatus::class);
    expect($t->status)->toBe(TenantStatus::Active);
});

test('Tenant disabled state 工厂', function () {
    $t = Tenant::factory()->disabled()->create();
    expect($t->status)->toBe(TenantStatus::Disabled);
});
