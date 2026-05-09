<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => '甲',
        'phone' => '13800000000',
        'password' => 'oldsecret123',
    ]);
    $this->actingAs($this->user, 'web');
});

test('GET /tenant/profile 渲染 Inertia 页面', function () {
    $this->get('/tenant/profile')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('tenant/Profile/Edit'));
});

test('PATCH /tenant/profile 改 name + phone', function () {
    $this->patch('/tenant/profile', ['name' => '新名', 'phone' => '13800009999'])
        ->assertRedirect();

    $this->user->refresh();
    expect($this->user->name)->toBe('新名');
    expect($this->user->phone)->toBe('13800009999');
});

test('PATCH /tenant/profile phone 与他人重复 422', function () {
    User::factory()->create(['phone' => '13800001111']);
    $this->patch('/tenant/profile', ['name' => 'X', 'phone' => '13800001111'])
        ->assertSessionHasErrors('phone');
});

test('PATCH /tenant/profile/password 改密码（current 校验）', function () {
    $this->patch('/tenant/profile/password', [
        'current_password' => 'oldsecret123',
        'password' => 'newsecret123',
        'password_confirmation' => 'newsecret123',
    ])->assertRedirect();

    $this->user->refresh();
    expect(Hash::check('newsecret123', $this->user->password))->toBeTrue();
});

test('PATCH /tenant/profile/password current 错误返回错误', function () {
    $this->patch('/tenant/profile/password', [
        'current_password' => 'WRONG',
        'password' => 'newsecret123',
        'password_confirmation' => 'newsecret123',
    ])->assertSessionHasErrors('current_password');
});

test('PATCH /tenant/profile/password new + confirmation 不一致 422', function () {
    $this->patch('/tenant/profile/password', [
        'current_password' => 'oldsecret123',
        'password' => 'newsecret123',
        'password_confirmation' => 'mismatch456',
    ])->assertSessionHasErrors('password');
});
