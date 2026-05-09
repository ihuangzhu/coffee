<?php

declare(strict_types=1);

use App\Modules\Authorization\AuthorizationServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Tenancy\TenancyServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    TenancyServiceProvider::class,
    AuthorizationServiceProvider::class,
    CatalogServiceProvider::class,
];
