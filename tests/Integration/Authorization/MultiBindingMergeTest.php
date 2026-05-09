<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('一人多 binding 权限并集生效（StoreManager@S1 + StoreClerk@S2）', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $s1 = Store::factory()->create(['tenant_id' => $t->id]);
    $s2 = Store::factory()->create(['tenant_id' => $t->id]);

    $manager = Role::query()->where('code', 'StoreManager')->whereNull('tenant_id')->firstOrFail();
    $clerk = Role::query()->where('code', 'StoreClerk')->whereNull('tenant_id')->firstOrFail();

    UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $manager->id, 'tenant_id' => $t->id, 'store_id' => $s1->id,
    ]);
    UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $clerk->id, 'tenant_id' => $t->id, 'store_id' => $s2->id,
    ]);

    Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/me/permissions');

    // StoreManager: roles.read, users.read, tenant.read, stores.read + 14 inventory perms + 5 BOM/produce perms
    // StoreClerk:   tenant.read, stores.read + 8 inventory perms + 3 BOM/produce perms (all subset of StoreManager)
    // 并集: StoreManager 的全部 23 个权限
    expect($resp->json('effective_permissions'))->toEqualCanonicalizing([
        'roles.read', 'users.read', 'tenant.read', 'stores.read',
        'items.read', 'items.write', 'item_skus.read', 'item_skus.write',
        'categories.read', 'categories.write',
        'inventory.read', 'inventory.adjust',
        'stocktake.write', 'damage.write',
        'stock_txn.read', 'stock_txn.reverse',
        'inventory_config.read', 'inventory_policy.read',
        'boms.read', 'boms.create', 'boms.update',
        'production.execute', 'production.read',
    ]);

    // role_bindings_count 反映两条 active
    $resp2 = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__tenant-probe');
    expect($resp2->json('role_bindings_count'))->toBe(2);
});
