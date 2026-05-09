<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 身份骨架（首期）：平台员工 + 2 租户 + 门店 + 员工 + memberships
            IdentitySkeletonSeeder::class,
            // RBAC 骨架：给已 seeded 用户分配预设角色
            RbacSkeletonSeeder::class,
        ]);
    }
}
