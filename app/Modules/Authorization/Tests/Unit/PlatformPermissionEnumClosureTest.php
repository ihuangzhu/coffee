<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\PlatformPermission;

test('PlatformPermission enum 所有 case 必须 platform. 前缀（INV-A）', function () {
    foreach (PlatformPermission::cases() as $case) {
        expect($case->value)->toStartWith('platform.');
    }
});

test('含首期 6 个平台权限码', function () {
    $values = array_map(fn ($c) => $c->value, PlatformPermission::cases());
    expect($values)->toContain(
        'platform.impersonate.full',
        'platform.impersonate.read-only',
        'platform.tenants.manage',
        'platform.stores.manage',
        'platform.users.manage',
        'platform.roles.manage',
    );
});
