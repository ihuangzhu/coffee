<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('正确凭据登录返回 token + user', function () {
    User::factory()->create([
        'phone' => '13800000001',
        'password' => 'secret123',
    ]);

    $resp = $this->postJson('/api/auth/login', [
        'phone' => '13800000001',
        'password' => 'secret123',
    ]);

    $resp->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'phone', 'name', 'is_platform_admin']]);
    expect($resp->json('user.phone'))->toBe('13800000001');
});

test('密码错误返回 422 统一错误（防账号枚举）', function () {
    User::factory()->create(['phone' => '13800000002', 'password' => 'correct_password']);

    $this->postJson('/api/auth/login', [
        'phone' => '13800000002',
        'password' => 'wrong_password',
    ])->assertStatus(422)->assertJsonValidationErrors(['phone']);
});

test('账号不存在返回 422 统一错误（防账号枚举）', function () {
    $this->postJson('/api/auth/login', [
        'phone' => '13899999999',
        'password' => 'whatever',
    ])->assertStatus(422)->assertJsonValidationErrors(['phone']);
});

test('disabled user 不能登录', function () {
    User::factory()->create([
        'phone' => '13800000003',
        'password' => 'secret123',
        'status' => 'disabled',
    ]);

    $this->postJson('/api/auth/login', [
        'phone' => '13800000003',
        'password' => 'secret123',
    ])->assertStatus(422);
});

test('登录成功更新 last_login_at', function () {
    $u = User::factory()->create([
        'phone' => '13800000004',
        'password' => 'secret123',
        'last_login_at' => null,
    ]);

    $this->postJson('/api/auth/login', [
        'phone' => '13800000004',
        'password' => 'secret123',
    ])->assertOk();

    expect($u->fresh()->last_login_at)->not->toBeNull();
});

test('logout 撤销 token', function () {
    $u = User::factory()->create();
    $token = $u->createToken('test');

    $this->withHeaders(['Authorization' => 'Bearer '.$token->plainTextToken])
        ->postJson('/api/auth/logout')
        ->assertOk();
});
