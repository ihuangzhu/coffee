<?php

declare(strict_types=1);

use App\Modules\Inventory\Models\Bom;
use App\Modules\Inventory\Models\BomComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('bom + components 行能创建并关联', function () {
    $bom = Bom::factory()->create(['output_qty' => 5]);
    BomComponent::factory()->count(3)->create(['bom_id' => $bom->id]);

    expect($bom->components()->count())->toBe(3);
    expect((float) $bom->output_qty)->toBe(5.0);
});

test('bom 软删除生效', function () {
    $bom = Bom::factory()->create();
    $bom->delete();

    expect(Bom::query()->withoutGlobalScopes()->withoutTrashed()->find($bom->id))->toBeNull();
});
