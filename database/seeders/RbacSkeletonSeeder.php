<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * 给 IdentitySkeletonSeeder 创建的用户分配预设角色：
 *   - 平台员工 13800000000 → PlatformSuperAdmin（platform_role_id）
 *   - 各租户 admin（13900000001/11）→ TenantAdmin
 *   - 各门店店长（13900000002/04）→ StoreManager（绑特定 store）
 *   - 店员（13900000003/12）→ StoreClerk
 *
 * 全部 firstOrCreate，幂等。依赖 IdentitySkeletonSeeder 先跑。
 */
class RbacSkeletonSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 平台员工
        $admin = User::query()->where('phone', '13800000000')->first();
        $super = PlatformRole::query()->where('code', 'PlatformSuperAdmin')->first();
        if ($admin && $super && ! $admin->platform_role_id) {
            $admin->update(['platform_role_id' => $super->id]);
        }

        // 2. 商户 admins
        $tenantAdminTplt = Role::query()->where('code', 'TenantAdmin')->whereNull('tenant_id')->first();
        $managerTplt = Role::query()->where('code', 'StoreManager')->whereNull('tenant_id')->first();
        $clerkTplt = Role::query()->where('code', 'StoreClerk')->whereNull('tenant_id')->first();

        $jiuhao = Tenant::query()->where('name', '九号咖啡')->first();
        $shifan = Tenant::query()->where('name', '示范咖啡')->first();

        // 九号咖啡管理员
        if ($u = User::query()->where('phone', '13900000001')->first()) {
            $this->bind($u->id, $tenantAdminTplt->id, $jiuhao->id, null);
        }
        // 九号咖啡 徐汇店店长
        if ($u = User::query()->where('phone', '13900000002')->first()) {
            $store = Store::query()->withoutGlobalScopes()
                ->where('tenant_id', $jiuhao->id)->where('name', '上海徐汇店')->first();
            $this->bind($u->id, $managerTplt->id, $jiuhao->id, $store?->id);
        }
        // 九号咖啡 徐汇店店员
        if ($u = User::query()->where('phone', '13900000003')->first()) {
            $store = Store::query()->withoutGlobalScopes()
                ->where('tenant_id', $jiuhao->id)->where('name', '上海徐汇店')->first();
            $this->bind($u->id, $clerkTplt->id, $jiuhao->id, $store?->id);
        }
        // 九号咖啡 朝阳店店长
        if ($u = User::query()->where('phone', '13900000004')->first()) {
            $store = Store::query()->withoutGlobalScopes()
                ->where('tenant_id', $jiuhao->id)->where('name', '北京朝阳店')->first();
            $this->bind($u->id, $managerTplt->id, $jiuhao->id, $store?->id);
        }
        // 示范咖啡 admin
        if ($u = User::query()->where('phone', '13900000011')->first()) {
            $this->bind($u->id, $tenantAdminTplt->id, $shifan->id, null);
        }
        // 示范咖啡 演示门店店员
        if ($u = User::query()->where('phone', '13900000012')->first()) {
            $store = Store::query()->withoutGlobalScopes()
                ->where('tenant_id', $shifan->id)->where('name', '演示门店')->first();
            $this->bind($u->id, $clerkTplt->id, $shifan->id, $store?->id);
        }

        $this->command?->info('=== RBAC fixture 已就绪 ===');
        $this->command?->info('平台员工 13800000000 → PlatformSuperAdmin');
        $this->command?->info('九号咖啡 admin/店长/店员 已绑预设角色');
        $this->command?->info('示范咖啡 admin/店员 已绑预设角色');
    }

    private function bind(string $userId, string $roleId, string $tenantId, ?string $storeId): void
    {
        $query = UserRoleBinding::query()->withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->where('tenant_id', $tenantId);
        $query = $storeId ? $query->where('store_id', $storeId) : $query->whereNull('store_id');

        if ($query->exists()) {
            return;
        }

        UserRoleBinding::query()->withoutGlobalScopes()->create([
            'user_id' => $userId,
            'role_id' => $roleId,
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'status' => 'active',
            'granted_at' => now(),
        ]);
    }
}
