<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('categories 默认创建顶级 BUSINESS 分类', function () {
    $cat = Category::factory()->create();
    expect($cat->id)->toBeString()->toHaveLength(26);
    expect($cat->owner_type->value)->toBe('TENANT');
    expect($cat->owner_store_id)->toBeNull();
    expect($cat->category_type->value)->toBe('BUSINESS');
    expect($cat->item_type_scope->value)->toBe('ALL');
    expect($cat->parent_id)->toBeNull();
    expect($cat->level)->toBe(1);
    expect($cat->path)->toBe('/');
    expect($cat->status->value)->toBe('active');
});

test('child factory 自动计算 path 与 level', function () {
    $root = Category::factory()->create();
    $child = Category::factory()->child($root)->create();
    $grand = Category::factory()->child($child)->create();
    expect($child->level)->toBe(2);
    expect($child->path)->toBe('/'.$root->id.'/');
    expect($grand->level)->toBe(3);
    expect($grand->path)->toBe('/'.$root->id.'/'.$child->id.'/');
});

test('computePathAndLevel: root 与 child 路径计算正确', function () {
    $root = Category::factory()->create();
    $rootCalc = (new Category())->computePathAndLevel(null);
    expect($rootCalc)->toBe(['path' => '/', 'level' => 1]);
    $childCalc = (new Category())->computePathAndLevel($root);
    expect($childCalc)->toBe([
        'path' => '/'.$root->id.'/',
        'level' => 2,
    ]);
});

test('inventory state 改为 INVENTORY 类型', function () {
    $cat = Category::factory()->inventory()->create();
    expect($cat->category_type->value)->toBe('INVENTORY');
});

test('softDeletes 生效', function () {
    $cat = Category::factory()->create();
    $cat->delete();
    // 默认查询（SoftDeletingScope 生效）找不到已删除记录
    expect(Category::query()->withoutGlobalScopes()->withoutTrashed()->find($cat->id))->toBeNull();
    // withTrashed 可以找回
    expect(Category::query()->withoutGlobalScopes()->withTrashed()->find($cat->id))
        ->not->toBeNull();
});

test('同 tenant + 同 owner + 同 parent 下 name 唯一冲突', function () {
    $tenantId = (string) Tenant::factory()->create()->id;
    // 使用 STORE owner（owner_store_id 非 NULL）+ 真实 parent_id，
    // 避免 SQLite 对 NULL 值在复合唯一索引中视为不同值的特性影响测试。
    $storeId = (string) \Illuminate\Support\Str::ulid();
    $parent = Category::factory()->create([
        'tenant_id' => $tenantId,
        'owner_type' => 'STORE',
        'owner_store_id' => $storeId,
        'parent_id' => null,
        'name' => '父分类',
    ]);
    Category::factory()->create([
        'tenant_id' => $tenantId,
        'owner_type' => 'STORE',
        'owner_store_id' => $storeId,
        'parent_id' => $parent->id,
        'name' => '饮品',
    ]);
    expect(fn () => Category::factory()->create([
        'tenant_id' => $tenantId,
        'owner_type' => 'STORE',
        'owner_store_id' => $storeId,
        'parent_id' => $parent->id,
        'name' => '饮品',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('同 tenant 下 code 唯一冲突；多个 NULL 不冲突', function () {
    $tenantId = (string) Tenant::factory()->create()->id;
    Category::factory()->create([
        'tenant_id' => $tenantId, 'code' => 'B-DRINK',
    ]);
    expect(fn () => Category::factory()->create([
        'tenant_id' => $tenantId, 'code' => 'B-DRINK',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
    Category::factory()->create(['tenant_id' => $tenantId, 'code' => null]);
    Category::factory()->create(['tenant_id' => $tenantId, 'code' => null]);
    expect(true)->toBeTrue();
});
