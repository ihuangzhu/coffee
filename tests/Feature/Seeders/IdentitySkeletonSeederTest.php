<?php

declare(strict_types=1);

use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Database\Seeders\IdentitySkeletonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeder 跑完后种子数据齐全', function () {
    $this->seed(IdentitySkeletonSeeder::class);

    expect(User::query()->where('phone', '13800000000')->where('is_platform_admin', true)->exists())->toBeTrue();
    expect(Tenant::query()->whereIn('name', ['九号咖啡', '示范咖啡'])->count())->toBe(2);

    $jiuhao = Tenant::query()->where('name', '九号咖啡')->firstOrFail();
    expect(Store::query()->withoutGlobalScopes()->where('tenant_id', $jiuhao->id)->count())->toBe(2);

    expect(Membership::query()->withoutGlobalScopes()
        ->where('user_id', User::where('phone', '13900000001')->value('id'))
        ->whereNull('store_id')->exists())->toBeTrue();
    expect(Membership::query()->withoutGlobalScopes()
        ->where('user_id', User::where('phone', '13900000002')->value('id'))
        ->whereNotNull('store_id')->exists())->toBeTrue();
});

test('seeder 重复执行幂等', function () {
    $this->seed(IdentitySkeletonSeeder::class);
    $userCountAfter1 = User::query()->count();
    $tenantCountAfter1 = Tenant::query()->count();
    $membershipCountAfter1 = Membership::query()->withoutGlobalScopes()->count();

    $this->seed(IdentitySkeletonSeeder::class);

    expect(User::query()->count())->toBe($userCountAfter1);
    expect(Tenant::query()->count())->toBe($tenantCountAfter1);
    expect(Membership::query()->withoutGlobalScopes()->count())->toBe($membershipCountAfter1);
});
