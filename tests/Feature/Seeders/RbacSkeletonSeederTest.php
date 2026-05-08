<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\User;
use Database\Seeders\IdentitySkeletonSeeder;
use Database\Seeders\RbacSkeletonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('RbacSkeletonSeeder 给 13800000000 平台员工绑 PlatformSuperAdmin', function () {
    $this->seed(IdentitySkeletonSeeder::class);
    $this->seed(RbacSkeletonSeeder::class);

    $admin = User::query()->where('phone', '13800000000')->firstOrFail();
    $super = PlatformRole::query()->where('code', 'PlatformSuperAdmin')->firstOrFail();
    expect($admin->platform_role_id)->toBe($super->id);
});

test('两个商户 admin (13900000001, 13900000011) 绑 TenantAdmin', function () {
    $this->seed(IdentitySkeletonSeeder::class);
    $this->seed(RbacSkeletonSeeder::class);

    foreach (['13900000001', '13900000011'] as $phone) {
        $u = User::query()->where('phone', $phone)->firstOrFail();
        $b = UserRoleBinding::query()->withoutGlobalScopes()
            ->where('user_id', $u->id)->where('status', 'active')
            ->with('role')->first();
        expect($b)->not->toBeNull();
        expect($b->role->code)->toBe('TenantAdmin');
    }
});

test('幂等：再跑一次 RBAC seeder 不增加 binding', function () {
    $this->seed(IdentitySkeletonSeeder::class);
    $this->seed(RbacSkeletonSeeder::class);
    $count1 = UserRoleBinding::query()->withoutGlobalScopes()->count();

    $this->seed(RbacSkeletonSeeder::class);
    expect(UserRoleBinding::query()->withoutGlobalScopes()->count())->toBe($count1);
});
