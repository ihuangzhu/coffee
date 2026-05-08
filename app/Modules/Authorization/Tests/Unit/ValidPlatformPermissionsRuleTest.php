<?php

declare(strict_types=1);

use App\Modules\Authorization\Rules\ValidPlatformPermissionsRule;
use Illuminate\Support\Facades\Validator;

function validateWithPlatformRule(mixed $value): array
{
    return Validator::make(
        ['perms' => $value],
        ['perms' => ['array', new ValidPlatformPermissionsRule]]
    )->errors()->all();
}

test('合法 PlatformPermission code 列表通过', function () {
    expect(validateWithPlatformRule(['platform.tenants.manage', 'platform.impersonate.full']))->toBe([]);
});

test('空数组通过', function () {
    expect(validateWithPlatformRule([]))->toBe([]);
});

test('拒绝商户域权限码（不带 platform. 前缀）', function () {
    $errors = validateWithPlatformRule(['roles.manage']);
    expect($errors)->not->toBeEmpty();
});

test('拒绝拼写错误的 platform.xxx 码', function () {
    $errors = validateWithPlatformRule(['platform.unknown.thing']);
    expect($errors)->not->toBeEmpty();
});

test('拒绝数组里出现非字符串元素', function () {
    $errors = validateWithPlatformRule(['platform.tenants.manage', 123]);
    expect($errors)->not->toBeEmpty();
});
