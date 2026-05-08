<?php

declare(strict_types=1);

namespace App\Support\Eloquent;

use App\Support\Tenancy\CurrentTenant;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = app(CurrentTenant::class)->require();
            }
        });
    }
}
