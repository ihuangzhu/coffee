<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\ItemSku;
use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\BomComponent;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('bom_components table has expected columns', function () {
    $cols = Schema::getColumnListing('bom_components');
    foreach ([
        'id', 'bom_id', 'component_sku_id', 'consume_qty', 'loss_rate', 'sequence_no',
    ] as $c) {
        expect($cols)->toContain($c);
    }
});

test('BomComponent casts are decimal:4 + int', function () {
    $tenant = Tenant::factory()->create();
    app(CurrentTenant::class)->set($tenant->id);
    $bom = Bom::factory()->create(['tenant_id' => $tenant->id]);
    $sku = ItemSku::factory()->create(['tenant_id' => $tenant->id]);
    $c = BomComponent::factory()->create([
        'bom_id' => $bom->id, 'component_sku_id' => $sku->id,
        'consume_qty' => '10.5', 'loss_rate' => '0.1', 'sequence_no' => 5,
    ]);
    expect($c->consume_qty)->toBe('10.5000');
    expect($c->loss_rate)->toBe('0.1000');
    expect($c->sequence_no)->toBe(5);
});

test('BomComponent componentSku + bom relations work', function () {
    $tenant = Tenant::factory()->create();
    app(CurrentTenant::class)->set($tenant->id);
    $bom = Bom::factory()->create(['tenant_id' => $tenant->id]);
    $sku = ItemSku::factory()->create(['tenant_id' => $tenant->id]);
    $c = BomComponent::factory()->create(['bom_id' => $bom->id, 'component_sku_id' => $sku->id]);

    expect($c->bom->id)->toBe($bom->id);
    expect($c->componentSku->id)->toBe($sku->id);
});

test('bom_components cascade on bom delete', function () {
    $tenant = Tenant::factory()->create();
    app(CurrentTenant::class)->set($tenant->id);
    $bom = Bom::factory()->create(['tenant_id' => $tenant->id]);
    $sku = ItemSku::factory()->create(['tenant_id' => $tenant->id]);
    BomComponent::factory()->create(['bom_id' => $bom->id, 'component_sku_id' => $sku->id]);

    // 物理删 bom（Bom 是 soft delete，所以走 forceDelete 测 cascade）
    $bom->forceDelete();
    expect(BomComponent::query()->count())->toBe(0);
});
