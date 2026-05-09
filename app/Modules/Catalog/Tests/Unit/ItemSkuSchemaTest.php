<?php
declare(strict_types=1);
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('item_skus 表能创建并关联 item', function () {
    $sku = ItemSku::factory()->create();

    expect($sku->id)->toHaveLength(26);
    expect($sku->item)->not->toBeNull();
    expect($sku->spec_json)->toBe([]);
});

test('barcode 同租户唯一（不同租户可重复）', function () {
    $t1 = Tenant::factory()->create();
    $t2 = Tenant::factory()->create();
    $i1 = Item::factory()->create(['tenant_id' => $t1->id]);
    $i2 = Item::factory()->create(['tenant_id' => $t2->id]);

    ItemSku::factory()->create(['tenant_id' => $t1->id, 'item_id' => $i1->id, 'barcode' => 'ABC123']);
    ItemSku::factory()->create(['tenant_id' => $t2->id, 'item_id' => $i2->id, 'barcode' => 'ABC123']);

    expect(true)->toBeTrue();

    expect(fn () => ItemSku::factory()->create([
        'tenant_id' => $t1->id, 'item_id' => $i1->id, 'barcode' => 'ABC123',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
