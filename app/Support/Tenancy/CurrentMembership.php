<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Modules\Identity\Models\Membership;

/**
 * 当前请求的 Membership 持有者（请求生命周期内的单例）。
 *
 * 平台员工 impersonation 时为 null。普通用户经 TenantMiddleware
 * 通过 user_id+tenant_id+status=active 解析后注入。
 */
class CurrentMembership
{
    private ?Membership $membership = null;

    public function set(?Membership $membership): void
    {
        $this->membership = $membership;
    }

    public function get(): ?Membership
    {
        return $this->membership;
    }
}
