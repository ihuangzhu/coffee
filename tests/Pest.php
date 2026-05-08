<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature', 'Integration');
uses(TestCase::class)->in('Unit');

// 将 RefreshDatabase 与 TestCase 扩展到模块测试目录
// app/Modules/**/Tests 中的 Feature 测试需要数据库事务隔离
// 注意：业务模块从 Task 4 起出现，此前目录不存在故需 is_dir() 守卫
if (is_dir(__DIR__.'/../app/Modules')) {
    uses(TestCase::class, RefreshDatabase::class)->in(__DIR__.'/../app/Modules');
}
