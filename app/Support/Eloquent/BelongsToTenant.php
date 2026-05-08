<?php

declare(strict_types=1);

namespace App\Support\Eloquent;

use App\Support\Tenancy\CurrentTenant;

/**
 * 业务模型必备 trait：
 * 1. 添加 TenantScope 全局作用域（自动按 tenant_id 过滤）
 * 2. 写入时若未显式赋值，自动注入 CurrentTenant 的 tenant_id
 *
 * Action / 调用方禁止手动赋 tenant_id；必须依赖此 trait 自动注入，
 * 防止误把别租户的 ID 写入。
 */
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
