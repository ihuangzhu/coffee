<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 身份骨架（首期）：平台员工 + 2 租户 + 门店 + 员工 + memberships
            IdentitySkeletonSeeder::class,
        ]);
    }
}
