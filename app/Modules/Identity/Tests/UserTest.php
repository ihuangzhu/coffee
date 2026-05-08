<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('User 工厂创建', function () {
    $u = User::factory()->create();
    expect($u->id)->toBeString()->toHaveLength(26);
    expect($u->is_platform_admin)->toBeFalse();
});

test('密码自动哈希', function () {
    $u = User::factory()->create(['password' => 'secret123']);
    expect($u->password)->not->toBe('secret123');
    expect(Hash::check('secret123', $u->password))->toBeTrue();
});

test('platformAdmin state', function () {
    $u = User::factory()->platformAdmin()->create();
    expect($u->is_platform_admin)->toBeTrue();
});

test('Sanctum 可发 token', function () {
    $u = User::factory()->create();
    $token = $u->createToken('test')->plainTextToken;
    expect($token)->toBeString()->not->toBeEmpty();
});

test('phone 全局唯一', function () {
    User::factory()->create(['phone' => '13800000001']);
    expect(fn () => User::factory()->create(['phone' => '13800000001']))
        ->toThrow(Exception::class);
});
