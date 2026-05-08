<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;

test('Permission enum 所有 case 均不带 platform. 前缀（INV-A）', function () {
    foreach (Permission::cases() as $case) {
        expect($case->value)->not->toStartWith('platform.');
    }
});

test('Permission enum 含首期 6 个权限码', function () {
    $values = array_map(fn ($c) => $c->value, Permission::cases());
    expect($values)->toContain('roles.read', 'roles.manage', 'users.read', 'users.assign-role', 'tenant.read', 'stores.read');
});
