<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Tenancy\CurrentMembership;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentTenant::class);
        $this->app->singleton(CurrentMembership::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 兼容老版 MySQL（key 长度上限 767 / 1000 字节）：限制默认 string 长度
        // 255 * 4(utf8mb4) = 1020 字节会触发 1071 错误，191 * 4 = 764 字节安全
        Schema::defaultStringLength(191);
    }
}
