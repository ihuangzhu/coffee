<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature', 'Integration');
uses(TestCase::class)->in('Unit');

// 将 RefreshDatabase 与 TestCase 扩展到模块测试目录
// app/Modules/**/Tests 中的 Feature 测试需要数据库事务隔离
uses(TestCase::class, RefreshDatabase::class)->in(__DIR__.'/../app/Modules');
