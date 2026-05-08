<?php

declare(strict_types=1);

namespace App\Support\Eloquent;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $id = app(CurrentTenant::class)->id();

        if ($id !== null) {
            $builder->where($model->qualifyColumn('tenant_id'), $id);
        }
    }
}
