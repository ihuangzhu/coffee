<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Tenancy\CurrentMembership;
use App\Support\Tenancy\CurrentTenant;
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
        //
    }
}
