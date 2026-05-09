<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('items 表能创建带默认值的行', function () {
    $item = Item::factory()->create();

    expect($item->id)->toBeString()->toHaveLength(26);
    expect($item->item_type->value)->toBe('SALE_PRODUCT');
    expect($item->owner_type->value)->toBe('TENANT');
    expect($item->status->value)->toBe('active');
    expect($item->sku_enabled)->toBeTrue();
    expect($item->inventory_enabled)->toBeTrue();
    expect($item->business_category_id)->toBeNull();
    expect($item->inventory_category_id)->toBeNull();
});

test('items 软删除生效', function () {
    $item = Item::factory()->create();
    $item->delete();

    expect(Item::query()->withoutGlobalScopes()->withoutTrashed()->find($item->id))->toBeNull();
    expect(Item::query()->withoutGlobalScopes()->withTrashed()->find($item->id))
        ->not->toBeNull();
});
