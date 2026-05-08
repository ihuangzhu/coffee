<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

/**
 * 当前请求所属的 tenant_id 持有者（请求生命周期内的单例）。
 *
 * 由 TenantMiddleware 在请求开始时通过 set() 注入；测试可直接 set。
 * id 为 null 时表示尚未认证（如登录路由），不应当强制隔离。
 */
class CurrentTenant
{
    private ?string $id = null;

    public function set(?string $id): void
    {
        $this->id = $id;
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function require(): string
    {
        if ($this->id === null) {
            throw new \RuntimeException('CurrentTenant 尚未设置，无法获取 tenant_id');
        }

        return $this->id;
    }
}
