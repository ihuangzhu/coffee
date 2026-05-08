<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

use Spatie\LaravelData\Data;

/**
 * 登录入参 DTO（不含 tenant_id：token 不绑租户）。
 *
 * 租户上下文由前端登录后调 /api/me/memberships 获取列表，
 * 后续每个业务请求带 X-Tenant-Id header 经 TenantMiddleware 解析。
 */
class LoginData extends Data
{
    public function __construct(
        /** 用户手机号（全局唯一登录账号） */
        public string $phone,
        /** 明文密码，由 Action 用 Hash::check 校验 */
        public string $password,
    ) {}
}
