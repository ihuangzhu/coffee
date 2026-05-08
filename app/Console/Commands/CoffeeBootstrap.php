<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Identity\Models\User;
use Illuminate\Console\Command;

/**
 * 初始化首个平台员工账号。
 *
 * 设计原则：
 * - 仅当数据库中尚无 is_platform_admin=true 用户时才允许执行
 * - 防止生产被误用做"创建任意 platform admin 后门"
 * - phone 必须不与已有 user 冲突
 */
class CoffeeBootstrap extends Command
{
    protected $signature = 'coffee:bootstrap {--phone= : 平台员工手机号} {--password= : 初始密码} {--name=平台管理员 : 显示名}';

    protected $description = '初始化首个平台员工账号（仅在尚无 platform admin 时可执行）';

    public function handle(): int
    {
        $phone = (string) $this->option('phone');
        $password = (string) $this->option('password');
        $name = (string) $this->option('name');

        if ($phone === '' || $password === '') {
            $this->error('--phone 与 --password 必填');

            return self::INVALID;
        }

        if (User::query()->where('is_platform_admin', true)->exists()) {
            $this->error('已存在 platform admin，coffee:bootstrap 拒绝二次执行');

            return self::FAILURE;
        }

        if (User::query()->where('phone', $phone)->exists()) {
            $this->error("phone {$phone} 已被占用");

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'phone' => $phone,
            'password' => $password,
            'status' => 'active',
            'is_platform_admin' => true,
        ]);

        $this->info("已创建 platform admin id={$user->id} phone={$user->phone}");
        $this->info('登录后调 POST /api/platform/tenants 即可创建首个租户。');

        return self::SUCCESS;
    }
}
