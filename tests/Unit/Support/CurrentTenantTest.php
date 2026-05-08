<?php

declare(strict_types=1);

use App\Support\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->ct = app(CurrentTenant::class);
});

test('id 默认 null', function () {
    expect($this->ct->id())->toBeNull();
});

test('set 后可读', function () {
    $this->ct->set('01HX...');
    expect($this->ct->id())->toBe('01HX...');
});

test('require 在未设置时抛出', function () {
    expect(fn () => $this->ct->require())->toThrow(RuntimeException::class);
});

test('require 在设置后返回 id', function () {
    $this->ct->set('TENANT-A');
    expect($this->ct->require())->toBe('TENANT-A');
});
