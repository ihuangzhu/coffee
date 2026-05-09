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

test('boms table has expected columns', function () {
    $cols = Schema::getColumnListing('boms');
    foreach ([
        'id', 'tenant_id', 'output_sku_id', 'output_qty', 'bom_type',
        'store_id', 'status', 'created_at', 'updated_at', 'deleted_at',
    ] as $c) {
        expect($cols)->toContain($c);
    }
});

test('Bom model uses HasUlid + BelongsToTenant + SoftDeletes', function () {
    $tenant = Tenant::factory()->create();
    app(CurrentTenant::class)->set($tenant->id);
    $sku = ItemSku::factory()->create(['tenant_id' => $tenant->id]);
    $bom = Bom::factory()->create([
        'tenant_id' => $tenant->id,
        'output_sku_id' => $sku->id,
    ]);

    expect($bom->id)->toHaveLength(26);
    expect($bom->bom_type->value)->toBe('STANDARD');
    expect($bom->output_qty)->toBe('1.0000');

    $bom->delete();
    expect(Bom::query()->find($bom->id))->toBeNull();
    expect(Bom::query()->withTrashed()->find($bom->id))->not->toBeNull();
});

test('Bom outputSku relation works', function () {
    $tenant = Tenant::factory()->create();
    app(CurrentTenant::class)->set($tenant->id);
    $sku = ItemSku::factory()->create(['tenant_id' => $tenant->id]);
    $bom = Bom::factory()->create(['tenant_id' => $tenant->id, 'output_sku_id' => $sku->id]);

    expect($bom->outputSku->id)->toBe($sku->id);
});

test('Bom components hasMany works', function () {
    $tenant = Tenant::factory()->create();
    app(CurrentTenant::class)->set($tenant->id);
    $bom = Bom::factory()->create(['tenant_id' => $tenant->id]);
    $compSku = ItemSku::factory()->create(['tenant_id' => $tenant->id]);
    BomComponent::factory()->create(['bom_id' => $bom->id, 'component_sku_id' => $compSku->id]);

    expect($bom->components)->toHaveCount(1);
});

test('BelongsToTenant scopes Bom by current tenant', function () {
    $t1 = Tenant::factory()->create();
    $t2 = Tenant::factory()->create();
    app(CurrentTenant::class)->set($t1->id);
    Bom::factory()->create(['tenant_id' => $t1->id]);

    app(CurrentTenant::class)->set($t2->id);
    Bom::factory()->create(['tenant_id' => $t2->id]);

    app(CurrentTenant::class)->set($t1->id);
    expect(Bom::query()->count())->toBe(1);

    app(CurrentTenant::class)->set($t2->id);
    expect(Bom::query()->count())->toBe(1);
});
