<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 身份骨架 dev fixture：
 *   - 1 个平台员工（与 coffee:bootstrap 默认手机号一致，避免双份）
 *   - 2 个租户：九号咖啡 / 示范咖啡
 *   - 每租户 1~2 个门店
 *   - 每租户 1 个 tenant 级管理员 + 每门店 1 个 store 级员工
 *
 * 全部使用 firstOrCreate 写入，重跑安全。
 * 测试登录账号见每条 'phone' 注释；统一密码 secret123。
 */
class IdentitySkeletonSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'secret123';

    public function run(): void
    {
        DB::transaction(function () {
            $this->seedPlatformAdmin();

            // 租户 1：九号咖啡
            $jiuhao = $this->seedTenant('九号咖啡');
            $jh_xuhui = $this->seedStore($jiuhao, '上海徐汇店');
            $jh_chaoyang = $this->seedStore($jiuhao, '北京朝阳店');

            $jhAdmin = $this->seedUser('13900000001', '张总');
            $this->seedMembership($jhAdmin, $jiuhao, null);

            $jhXuhuiManager = $this->seedUser('13900000002', '李店长');
            $this->seedMembership($jhXuhuiManager, $jiuhao, $jh_xuhui);

            $jhXuhuiStaff = $this->seedUser('13900000003', '王店员');
            $this->seedMembership($jhXuhuiStaff, $jiuhao, $jh_xuhui);

            $jhChaoyangManager = $this->seedUser('13900000004', '陈店长');
            $this->seedMembership($jhChaoyangManager, $jiuhao, $jh_chaoyang);

            // 租户 2：示范咖啡
            $shifan = $this->seedTenant('示范咖啡');
            $sf_demo = $this->seedStore($shifan, '演示门店');

            $sfAdmin = $this->seedUser('13900000011', '赵总');
            $this->seedMembership($sfAdmin, $shifan, null);

            $sfStaff = $this->seedUser('13900000012', '刘店员');
            $this->seedMembership($sfStaff, $shifan, $sf_demo);
        });

        $this->command?->info('=== 身份骨架 fixture 已就绪 ===');
        $this->command?->info('平台员工: 13800000000 / '.self::DEFAULT_PASSWORD);
        $this->command?->info('九号咖啡 admin: 13900000001 / '.self::DEFAULT_PASSWORD);
        $this->command?->info('九号咖啡 徐汇店店长: 13900000002 / '.self::DEFAULT_PASSWORD);
        $this->command?->info('示范咖啡 admin: 13900000011 / '.self::DEFAULT_PASSWORD);
    }

    private function seedPlatformAdmin(): void
    {
        User::query()->firstOrCreate(
            ['phone' => '13800000000'],
            [
                'name' => '平台员工',
                'password' => self::DEFAULT_PASSWORD,
                'status' => 'active',
                'is_platform_admin' => true,
            ]
        );
    }

    private function seedTenant(string $name): Tenant
    {
        return Tenant::query()->firstOrCreate(
            ['name' => $name],
            ['status' => 'active']
        );
    }

    private function seedStore(Tenant $tenant, string $name): Store
    {
        return Store::query()->withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $name],
            ['status' => 'active']
        );
    }

    private function seedUser(string $phone, string $name): User
    {
        return User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'name' => $name,
                'password' => self::DEFAULT_PASSWORD,
                'status' => 'active',
                'is_platform_admin' => false,
            ]
        );
    }

    private function seedMembership(User $user, Tenant $tenant, ?Store $store): Membership
    {
        // store_id NULL 在 SQL 不等于 NULL，firstOrCreate 默认不能去重 NULL 列；
        // 这里手动判断已存在的 active 绑定再决定是否插入。
        $query = Membership::query()->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id);
        $query = $store ? $query->where('store_id', $store->id) : $query->whereNull('store_id');

        $existing = $query->first();
        if ($existing) {
            return $existing;
        }

        return Membership::query()->withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'store_id' => $store?->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }
}
