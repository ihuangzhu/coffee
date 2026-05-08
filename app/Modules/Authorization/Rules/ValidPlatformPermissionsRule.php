<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Rules;

use App\Modules\Authorization\Enums\PlatformPermission;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * platform_roles.permissions 写入校验：
 *   - 必须是数组
 *   - 每个元素必须是 PlatformPermission enum 合法值
 *   - 显式拒绝任何不带 platform. 前缀的 code（INV-B 双向校验）
 */
class ValidPlatformPermissionsRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('权限列表必须为数组');

            return;
        }

        $allowed = array_map(fn (PlatformPermission $p) => $p->value, PlatformPermission::cases());

        foreach ($value as $code) {
            if (! is_string($code)) {
                $fail('权限码必须是字符串，实际收到：'.get_debug_type($code));

                return;
            }
            if (! str_starts_with($code, 'platform.')) {
                $fail("平台角色权限码必须 platform. 前缀：{$code}");

                return;
            }
            if (! in_array($code, $allowed, strict: true)) {
                $fail("非法平台权限码：{$code}");

                return;
            }
        }
    }
}
