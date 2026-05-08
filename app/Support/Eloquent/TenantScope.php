<?php

declare(strict_types=1);

namespace App\Support\Eloquent;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * 多租户隔离全局作用域。
 *
 * 任何业务模型查询自动追加 WHERE tenant_id = <当前租户>。
 * CurrentTenant 为空时不追加 WHERE——兼容登录前的合法查询场景。
 */
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
