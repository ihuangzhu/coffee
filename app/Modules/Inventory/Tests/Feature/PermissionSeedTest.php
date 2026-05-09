<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;
use App\Modules\Authorization\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Permission enum contains all 22 tenant cases', function () {
    $expected = [
        'items.read', 'items.write',
        'item_skus.read', 'item_skus.write',
        'categories.read', 'categories.write',
        'inventory.read', 'inventory.adjust',
        'stocktake.write', 'damage.write',
        'stock_txn.read', 'stock_txn.reverse',
        'inventory_config.read', 'inventory_config.update',
        'inventory_policy.read', 'inventory_policy.update',
        'boms.read', 'boms.create', 'boms.update', 'boms.delete',
        'production.execute', 'production.read',
    ];
    $values = array_column(Permission::cases(), 'value');
    foreach ($expected as $perm) {
        expect($values)->toContain($perm);
    }
});

test('TenantAdmin role contains items.read, inventory_config.update, and stock_txn.reverse', function () {
    $role = Role::query()->whereNull('tenant_id')->where('code', 'TenantAdmin')->firstOrFail();

    expect($role->permissions)->toContain('items.read');
    expect($role->permissions)->toContain('inventory_config.update');
    expect($role->permissions)->toContain('stock_txn.reverse');
});

test('StoreClerk does not contain stock_txn.reverse, damage.write, inventory_config.update but does contain inventory.adjust', function () {
    $role = Role::query()->whereNull('tenant_id')->where('code', 'StoreClerk')->firstOrFail();

    expect($role->permissions)->not->toContain('stock_txn.reverse');
    expect($role->permissions)->not->toContain('damage.write');
    expect($role->permissions)->not->toContain('inventory_config.update');
    expect($role->permissions)->toContain('inventory.adjust');
});

test('TenantAdmin role contains all 6 BOM/produce permissions', function () {
    $role = Role::query()->whereNull('tenant_id')->where('code', 'TenantAdmin')->firstOrFail();

    foreach (['boms.read', 'boms.create', 'boms.update', 'boms.delete',
              'production.execute', 'production.read'] as $perm) {
        expect($role->permissions)->toContain($perm);
    }
});

test('StoreManager has BOM CRU + production but not boms.delete', function () {
    $role = Role::query()->whereNull('tenant_id')->where('code', 'StoreManager')->firstOrFail();

    expect($role->permissions)->toContain('boms.read');
    expect($role->permissions)->toContain('boms.create');
    expect($role->permissions)->toContain('boms.update');
    expect($role->permissions)->toContain('production.execute');
    expect($role->permissions)->toContain('production.read');
    expect($role->permissions)->not->toContain('boms.delete');
});

test('StoreClerk has only boms.read + production.read + production.execute', function () {
    $role = Role::query()->whereNull('tenant_id')->where('code', 'StoreClerk')->firstOrFail();

    expect($role->permissions)->toContain('boms.read');
    expect($role->permissions)->toContain('production.read');
    expect($role->permissions)->toContain('production.execute');
    expect($role->permissions)->not->toContain('boms.create');
    expect($role->permissions)->not->toContain('boms.update');
    expect($role->permissions)->not->toContain('boms.delete');
});
