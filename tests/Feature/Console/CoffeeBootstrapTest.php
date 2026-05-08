<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('coffee:bootstrap 首次执行成功', function () {
    $this->artisan('coffee:bootstrap', [
        '--phone' => '13800000000',
        '--password' => 'secret123',
    ])->assertSuccessful();

    $u = User::query()->where('phone', '13800000000')->firstOrFail();
    expect($u->is_platform_admin)->toBeTrue();
});

test('已有 platform admin 时拒绝执行', function () {
    User::factory()->platformAdmin()->create();

    $this->artisan('coffee:bootstrap', [
        '--phone' => '13800000001',
        '--password' => 'secret123',
    ])->assertFailed();
});

test('缺 phone/password 返回 INVALID', function () {
    $this->artisan('coffee:bootstrap')->assertExitCode(2);
});

test('phone 已被占用拒绝', function () {
    User::factory()->create(['phone' => '13800000002']);

    $this->artisan('coffee:bootstrap', [
        '--phone' => '13800000002',
        '--password' => 'secret123',
    ])->assertFailed();
});
