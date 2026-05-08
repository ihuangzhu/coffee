<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Rules;

use App\Modules\Authorization\Enums\Permission;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * roles.permissions 写入校验：
 *   - 必须是数组
 *   - 每个元素必须是 Permission enum 合法值
 *   - 显式拒绝任何带 platform. 前缀的 code（INV-B 双向校验）
 */
class ValidPermissionsRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('权限列表必须为数组');
            return;
        }

        $allowed = array_map(fn (Permission $p) => $p->value, Permission::cases());

        foreach ($value as $code) {
            if (! is_string($code)) {
                $fail("权限码必须是字符串：{$code}");
                return;
            }
            if (str_starts_with($code, 'platform.')) {
                $fail("不允许在商户角色中写入平台权限码：{$code}");
                return;
            }
            if (! in_array($code, $allowed, strict: true)) {
                $fail("非法权限码：{$code}");
                return;
            }
        }
    }
}
