<?php

declare(strict_types=1);

use App\Modules\Authorization\Rules\ValidPermissionsRule;
use Illuminate\Support\Facades\Validator;

function validateWithRule(mixed $value): array
{
    return Validator::make(
        ['perms' => $value],
        ['perms' => ['array', new ValidPermissionsRule]]
    )->errors()->all();
}

test('合法 Permission code 列表通过', function () {
    expect(validateWithRule(['roles.read', 'users.read']))->toBe([]);
});

test('空数组通过', function () {
    expect(validateWithRule([]))->toBe([]);
});

test('拒绝拼写错误的 code', function () {
    $errors = validateWithRule(['roles.unknown']);
    expect($errors)->not->toBeEmpty();
});

test('拒绝任何 platform.* 前缀（INV-B 双向校验）', function () {
    $errors = validateWithRule(['platform.tenants.manage']);
    expect($errors)->not->toBeEmpty();
});

test('拒绝混合（一条 platform.* + 一条合法）', function () {
    $errors = validateWithRule(['roles.read', 'platform.impersonate.full']);
    expect($errors)->not->toBeEmpty();
});

test('拒绝数组里出现非字符串元素', function () {
    $errors = validateWithRule(['roles.read', 123]);
    expect($errors)->not->toBeEmpty();
});
