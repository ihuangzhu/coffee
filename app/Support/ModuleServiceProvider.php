<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\ServiceProvider;

/**
 * 模块 ServiceProvider 基类。
 *
 * 子类只需实现 modulePath() 返回模块根目录绝对路径（通常 __DIR__），
 * 基类自动注册：
 *   - Routes/api.php   → 加载为路由
 *   - Database/Migrations  → 注册为迁移目录
 *
 * 模块特有的事件监听器、绑定等仍在子类的 boot() / register() 中显式定义。
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * 返回模块根目录绝对路径。
     */
    abstract protected function modulePath(): string;

    public function boot(): void
    {
        $path = $this->modulePath();

        $routes = $path.'/Routes/api.php';
        if (is_file($routes)) {
            $this->loadRoutesFrom($routes);
        }

        $migrations = $path.'/Database/Migrations';
        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }
    }
}
