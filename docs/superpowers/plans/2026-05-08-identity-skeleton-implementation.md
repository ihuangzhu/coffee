# Coffee 身份骨架实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在 `/mnt/d/Projects/Huang/coffee` 实现 base.md §1~§2 / §6.1 的最小可跑多租户身份骨架（5 表 / 9 端点 / 3 前端页 / 1 CLI），框架资产从 `/mnt/d/Projects/Php/service/supermarket` 选择性移植，业务模块全部按 base.md 重写。

**Architecture:** Laravel 13 模块化单体，`app/Modules/{Identity,Tenancy}` 双模块 + `app/Support/{Eloquent,Tenancy}` 多租户基础设施。Sanctum personal access token + `X-Tenant-Id` header + `BelongsToTenant` trait + `TenantScope` 全局作用域强制租户隔离。

**Tech Stack:** PHP 8.3+ / Laravel 13 / Sanctum 4 / Spatie Data 4 / Pest 4 / Vue 3 + Inertia 2 + Pinia + Element Plus + Tailwind v4 + Vite 5 + TypeScript

---

## 前置约定

**工作目录**：所有相对路径基于 `/mnt/d/Projects/Huang/coffee`，下文简称 `<root>`。
**源仓库参考**：所有 "from supermarket" 路径基于 `/mnt/d/Projects/Php/service/supermarket`，下文简称 `<sm>`。
**Git 分支**：直接在 `main` 上工作（首期项目，单分支推进；若用户已开 worktree 则以 worktree 路径为准）。
**提交规范**：中文 conventional commits（feat/fix/refactor/test/chore/docs），不允许 `--no-verify`。
**测试数据库**：SQLite `:memory:`（覆盖 supermarket 默认的 MySQL，降低首期环境门槛）。
**Spec 来源**：`docs/superpowers/specs/2026-05-07-identity-skeleton-design.md`（每个任务标注对应章节）。

**重要术语映射**（执行中频繁用到）：

| supermarket 命名 | coffee 命名 | 出现位置 |
|---|---|---|
| `Merchant` / `merchant_id` / `merchants` | `Tenant` / `tenant_id` / `tenants` | 全文 |
| `BelongsToMerchant` / `MerchantScope` | `BelongsToTenant` / `TenantScope` | trait/scope |
| `CurrentMerchant` | `CurrentTenant` | Tenancy 单例 |
| `MerchantUser` | `Membership` | 关系表 |
| `X-Merchant-Id` header | `X-Tenant-Id` header | TenantMiddleware |
| `app/Modules/Auth` | `app/Modules/Identity` | 模块根 |
| `app/Modules/Merchant` | `app/Modules/Tenancy` | 模块根 |

---

## Task 0: 前置确认 git 身份

**Files:** （无）

- [ ] **Step 0.1: 确认 git user 已配置**

Run: `cd /mnt/d/Projects/Huang/coffee && git config user.email && git config user.name`
Expected: 输出邮箱 + 名字（若空则用户已被提示设置）。**若为空，停止并 BLOCKED 上报，要求用户设置后再继续。**

- [ ] **Step 0.2: 确认 main 分支位于"仅 docs"提交后**

Run: `git log --oneline 2>&1 | head -3`
Expected: 至少有 1 个 commit；若没有则把 `docs/` 作为第一个提交。

```bash
git log --oneline 2>&1 | head -3
# 若 fatal: branch has no commits ：
git add docs/ && git commit -m "docs: 初始化 coffee 项目并写入身份骨架设计与实施计划"
```

---

## Task 1: 初始化 Laravel 13 骨架 + 移植框架级配置

**目的**：把 `<root>` 从"仅 docs/" 升级为"可跑 `php artisan --version` 的 Laravel 13 项目"。

**Files:**
- Create: `<root>/composer.json` `<root>/package.json` `<root>/.env.example` `<root>/artisan` `<root>/phpunit.xml` `<root>/pint.json` `<root>/vite.config.ts` `<root>/tailwind.config.js` `<root>/tsconfig.json` `<root>/eslint.config.js` `<root>/.editorconfig` `<root>/.gitignore` `<root>/.gitattributes` `<root>/.npmrc`
- Create: `<root>/bootstrap/app.php` `<root>/bootstrap/providers.php` `<root>/public/index.php` `<root>/public/.htaccess`
- Create: `<root>/config/{app,auth,cache,database,filesystems,logging,queue,sanctum,services,session,view}.php`（用 Laravel 13 默认）
- Create: `<root>/routes/{web,console}.php`
- Create: `<root>/storage/{app,framework,logs}/...`（标准 Laravel 目录）
- Create: `<root>/database/database.sqlite`（开发用空文件）
- Create: `<root>/resources/views/welcome.blade.php`（占位）
- Create: `<root>/tests/Pest.php` `<root>/tests/TestCase.php`

- [ ] **Step 1.1: 用 composer create-project 在临时目录拉 Laravel 13 骨架**

```bash
cd /tmp && rm -rf coffee-skeleton && \
  composer create-project --prefer-dist laravel/laravel coffee-skeleton "^13.0"
```
Expected: `/tmp/coffee-skeleton` 出现完整 Laravel 项目。失败则 BLOCKED（网络/composer 问题）。

- [ ] **Step 1.2: 把骨架文件搬入 `<root>`，跳过会冲突的目录**

```bash
cd /mnt/d/Projects/Huang/coffee && \
  rsync -av --exclude='docs' --exclude='.git' --exclude='vendor' --exclude='node_modules' \
    /tmp/coffee-skeleton/ ./
```
Expected: `<root>/app/`、`<root>/bootstrap/`、`<root>/config/` 等出现；`<root>/docs/` 保持不变。

- [ ] **Step 1.3: 用 supermarket 的 composer.json 替换** （除 namespace 和应用名）

读 `<sm>/composer.json` → 写 `<root>/composer.json`，仅做以下修改：
- `name`: 改为 `huang/coffee`
- 其它 `require` / `require-dev` / `scripts` / `autoload` / `autoload-dev` / `config` 完全保留

```bash
cp <sm>/composer.json <root>/composer.json
# 然后用 sed 改 name 字段
sed -i 's|"laravel/laravel"|"huang/coffee"|' <root>/composer.json
# 验证
grep '"name"' <root>/composer.json
```
Expected: `"name": "huang/coffee",`

- [ ] **Step 1.4: 替换 package.json / vite.config.ts / tailwind.config.js / tsconfig.json / eslint.config.js / pint.json / phpunit.xml / .editorconfig**

```bash
for f in package.json vite.config.ts tailwind.config.js tsconfig.json eslint.config.js pint.json .editorconfig .gitattributes .npmrc; do
  cp <sm>/$f <root>/$f
done

# package.json 改 name
sed -i 's|"supermarket-ui"|"coffee-ui"|' <root>/package.json
```

- [ ] **Step 1.5: 把 phpunit.xml 切到 SQLite :memory:**

复制 `<sm>/phpunit.xml` 到 `<root>/phpunit.xml`，然后修改 DB 段（spec §8.5 决定）：

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<!-- 删除 DB_URL 行 -->
```

- [ ] **Step 1.6: .env.example 调整**

复制 `<sm>/.env.example` → `<root>/.env.example`，关键修改：
- `APP_NAME=Coffee`
- `DB_CONNECTION=sqlite`，注释掉 mysql 相关
- `DB_DATABASE=/mnt/d/Projects/Huang/coffee/database/database.sqlite`（绝对路径，避免 cli/web 工作目录差异）

- [ ] **Step 1.7: 用 supermarket 的 bootstrap/app.php 模板，但只保留 tenant 相关别名**

写 `<root>/bootstrap/app.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\TenantMiddleware;
use App\Modules\Identity\Http\Middleware\PlatformAdminMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => TenantMiddleware::class,
            'platform_admin' => PlatformAdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

注意：`TenantMiddleware` 与 `PlatformAdminMiddleware` 在 Task 8 / Task 12 才创建。Task 1 完成后 `php artisan --version` 会因 import 不存在而 fail —— 这是预期，下一任务即修复。**或者** 暂时把 `alias` 段留空，等 Task 8/12 时再补。**采用后者更稳**：Step 1.7 改为 `alias` 段空数组。

- [ ] **Step 1.8: 安装依赖 + 生成 key + 测试一切就绪**

```bash
cd <root>
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan --version
```
Expected: `Laravel Framework 13.x.x` 输出。

- [ ] **Step 1.9: 删除 Laravel 默认骨架里此项目用不到的文件**

```bash
cd <root>
# 删默认 User model（我们将在 Task 6 重写到 Modules/Identity）
rm -f app/Models/User.php
# 删默认 user 迁移（我们将在 Task 6 重写到 Modules/Identity/Database/Migrations）
rm -f database/migrations/0001_01_01_000000_create_users_table.php
# 删默认 user factory
rm -f database/factories/UserFactory.php
# 删默认欢迎页路由（保留 welcome.blade.php 文件不影响）
```

- [ ] **Step 1.10: 把 supermarket 的 .gitignore 模板叠加进来**

```bash
cp <sm>/.gitignore <root>/.gitignore
```

- [ ] **Step 1.11: Commit**

```bash
cd <root>
git add .
git commit -m "chore: 初始化 Laravel 13 骨架并移植 supermarket 框架级配置

- 复制 supermarket composer.json/package.json/vite/tailwind/tsconfig/eslint/pint/phpunit
- bootstrap/app.php 留空 alias 段（中间件类待 Task 8/12 实现）
- phpunit.xml 切换到 SQLite :memory:
- 删除 Laravel 默认 User model/migration/factory（将在 Identity 模块重写）"
```

---

## Task 2: 发布 Sanctum migration + 基础 config

**目的**：让 `php artisan migrate` 能产出 `personal_access_tokens` 表。

**Files:**
- Create: `<root>/database/migrations/0001_01_01_000001_create_cache_table.php`（Laravel 13 默认）
- Create: `<root>/database/migrations/0001_01_01_000002_create_jobs_table.php`（Laravel 13 默认）
- Create: `<root>/database/migrations/<timestamp>_create_personal_access_tokens_table.php`

- [ ] **Step 2.1: 拷贝 supermarket 的 personal_access_tokens migration**

```bash
cp <sm>/database/migrations/2026_04_30_015829_create_personal_access_tokens_table.php \
   <root>/database/migrations/2026_05_08_000001_create_personal_access_tokens_table.php
```

- [ ] **Step 2.2: 确认 cache + jobs migrations 存在（Laravel 13 默认）**

```bash
ls <root>/database/migrations/0001_01_01_*
```
Expected: `0001_01_01_000001_create_cache_table.php` `0001_01_01_000002_create_jobs_table.php`。如缺失，从 `/tmp/coffee-skeleton/database/migrations/` 补回。

- [ ] **Step 2.3: 跑迁移验证**

```bash
cd <root>
php artisan migrate --force
```
Expected: `Migrated: ..._create_cache_table` `..._create_jobs_table` `..._create_personal_access_tokens_table` 三行。

- [ ] **Step 2.4: 写一个最小烟雾测试确认 Pest 能跑**

Create `<root>/tests/Unit/SmokeTest.php`：

```php
<?php

declare(strict_types=1);

test('framework boots', function () {
    expect(app())->not->toBeNull();
    expect(config('app.name'))->toBe('Coffee');
});
```

Run: `cd <root> && ./vendor/bin/pest tests/Unit/SmokeTest.php`
Expected: 1 test passed。

- [ ] **Step 2.5: Commit**

```bash
git add database/migrations tests/Unit/SmokeTest.php
git commit -m "feat: 移植 personal_access_tokens 迁移并添加冒烟测试"
```

---

## Task 3: 移植 Support 层（HasUlid / Tenancy 单例 / BelongsToTenant trait + Scope / 异常基类 / ModuleServiceProvider）

**Spec:** §5

**Files:**
- Create: `<root>/app/Support/Eloquent/HasUlid.php`
- Create: `<root>/app/Support/Eloquent/BelongsToTenant.php`
- Create: `<root>/app/Support/Eloquent/TenantScope.php`
- Create: `<root>/app/Support/Tenancy/CurrentTenant.php`
- Create: `<root>/app/Support/Tenancy/CurrentMembership.php`
- Create: `<root>/app/Support/ModuleServiceProvider.php`
- Create: `<root>/app/Support/Exceptions/BusinessException.php`
- Test: `<root>/tests/Unit/Support/CurrentTenantTest.php`

- [ ] **Step 3.1: 写 HasUlid trait（直拷贝 supermarket，无改动）**

写 `<root>/app/Support/Eloquent/HasUlid.php`，内容与 `<sm>/app/Support/Eloquent/HasUlid.php` 100% 相同（已读过，是通用 ULID trait）。

- [ ] **Step 3.2: 写 CurrentTenant 单例**

写 `<root>/app/Support/Tenancy/CurrentTenant.php`：

```php
<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

class CurrentTenant
{
    private ?string $id = null;

    public function set(?string $id): void
    {
        $this->id = $id;
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function require(): string
    {
        if ($this->id === null) {
            throw new \RuntimeException('CurrentTenant 尚未设置，无法获取 tenant_id');
        }

        return $this->id;
    }
}
```

注：Laravel 默认在每个 HTTP 请求会重置容器，但**测试中** `RefreshDatabase` + 多请求共用同一容器时 `CurrentTenant` 可能跨请求残留 → 这正是 Task 8 `withoutGlobalScopes` 防御的原因。

- [ ] **Step 3.3: 写 CurrentMembership 单例（持有 Membership 实例或 null）**

写 `<root>/app/Support/Tenancy/CurrentMembership.php`：

```php
<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Modules\Identity\Models\Membership;

class CurrentMembership
{
    private ?Membership $membership = null;

    public function set(?Membership $membership): void
    {
        $this->membership = $membership;
    }

    public function get(): ?Membership
    {
        return $this->membership;
    }
}
```

注：此处引用了 Task 7 才定义的 `Membership`。在 Task 7 完成前，本类不会被实例化（即使 `composer dump-autoload` 生成元数据，PHP 不会真正加载该 class）。如果 IDE 报 unresolved symbol，**忽略**——是预期。

- [ ] **Step 3.4: 写 TenantScope**

写 `<root>/app/Support/Eloquent/TenantScope.php`：

```php
<?php

declare(strict_types=1);

namespace App\Support\Eloquent;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $id = app(CurrentTenant::class)->id();

        if ($id !== null) {
            $builder->where($model->qualifyColumn('tenant_id'), $id);
        }
    }
}
```

- [ ] **Step 3.5: 写 BelongsToTenant trait**

写 `<root>/app/Support/Eloquent/BelongsToTenant.php`：

```php
<?php

declare(strict_types=1);

namespace App\Support\Eloquent;

use App\Support\Tenancy\CurrentTenant;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = app(CurrentTenant::class)->require();
            }
        });
    }
}
```

- [ ] **Step 3.6: 写 ModuleServiceProvider 基类（直拷 supermarket）**

`<root>/app/Support/ModuleServiceProvider.php` 内容同 `<sm>/app/Support/ModuleServiceProvider.php`（无任何业务耦合）。

- [ ] **Step 3.7: 写 BusinessException 基类**

写 `<root>/app/Support/Exceptions/BusinessException.php`：

```php
<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use RuntimeException;

class BusinessException extends RuntimeException
{
    public function __construct(
        protected string $errorCode,
        string $message = '',
        protected int $httpStatus = 400,
        protected array $details = [],
    ) {
        parent::__construct($message ?: $errorCode);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function details(): array
    {
        return $this->details;
    }
}
```

- [ ] **Step 3.8: Pest 测试 CurrentTenant**

写 `<root>/tests/Unit/Support/CurrentTenantTest.php`：

```php
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
```

Run: `cd <root> && ./vendor/bin/pest tests/Unit/Support/CurrentTenantTest.php`
Expected: 4 tests passed.

- [ ] **Step 3.9: Commit**

```bash
git add app/Support tests/Unit/Support
git commit -m "feat(support): 移植多租户基础设施（HasUlid/BelongsToTenant/TenantScope/CurrentTenant/CurrentMembership）"
```

---

## Task 4: Tenancy 模块 - Tenant 实体（迁移 + 模型 + 工厂 + ServiceProvider + 测试）

**Spec:** §3.1

**Files:**
- Create: `<root>/app/Modules/Tenancy/TenancyServiceProvider.php`
- Create: `<root>/app/Modules/Tenancy/Enums/TenantStatus.php`
- Create: `<root>/app/Modules/Tenancy/Models/Tenant.php`
- Create: `<root>/app/Modules/Tenancy/Database/Migrations/2026_05_08_000010_create_tenants_table.php`
- Create: `<root>/app/Modules/Tenancy/Database/Factories/TenantFactory.php`
- Create: `<root>/app/Modules/Tenancy/Tests/TenantTest.php`
- Modify: `<root>/bootstrap/providers.php`（注册 TenancyServiceProvider）

- [ ] **Step 4.1: 写 TenantStatus enum**

```php
<?php
// <root>/app/Modules/Tenancy/Enums/TenantStatus.php
declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

enum TenantStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
```

- [ ] **Step 4.2: 写迁移**

```php
<?php
// <root>/app/Modules/Tenancy/Database/Migrations/2026_05_08_000010_create_tenants_table.php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name', 120);
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
```

- [ ] **Step 4.3: 写 Tenant 模型（不挂 BelongsToTenant —— 它是租户根）**

```php
<?php
// <root>/app/Modules/Tenancy/Models/Tenant.php
declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Database\Factories\TenantFactory;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'tenants';
    protected $guarded = [];
    protected $casts = ['status' => TenantStatus::class];

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
```

- [ ] **Step 4.4: 写 Factory**

```php
<?php
// <root>/app/Modules/Tenancy/Database/Factories/TenantFactory.php
declare(strict_types=1);

namespace App\Modules\Tenancy\Database\Factories;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' 咖啡',
            'status' => 'active',
        ];
    }

    public function disabled(): self
    {
        return $this->state(['status' => 'disabled']);
    }
}
```

- [ ] **Step 4.5: 写 TenancyServiceProvider**

```php
<?php
// <root>/app/Modules/Tenancy/TenancyServiceProvider.php
declare(strict_types=1);

namespace App\Modules\Tenancy;

use App\Support\ModuleServiceProvider;

class TenancyServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }
}
```

- [ ] **Step 4.6: 注册 ServiceProvider**

读 `<root>/bootstrap/providers.php`（Laravel 13 默认产物，单数组），追加 `App\Modules\Tenancy\TenancyServiceProvider::class`：

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Modules\Tenancy\TenancyServiceProvider::class,
];
```

- [ ] **Step 4.7: 跑迁移确认 tenants 表生成**

```bash
cd <root>
php artisan migrate --force
php artisan tinker --execute="echo \DB::select('PRAGMA table_info(tenants)') ? 'ok' : 'fail';"
```
Expected: 输出非空（SQLite 表结构查询成功）。

- [ ] **Step 4.8: TDD 写 Tenant 测试**

写 `<root>/app/Modules/Tenancy/Tests/TenantTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Tenant 创建生成 ULID 主键', function () {
    $t = Tenant::factory()->create(['name' => '九号咖啡']);
    expect($t->id)->toBeString()->toHaveLength(26);
    expect($t->name)->toBe('九号咖啡');
});

test('Tenant status 转换为 enum', function () {
    $t = Tenant::factory()->create();
    expect($t->status)->toBeInstanceOf(TenantStatus::class);
    expect($t->status)->toBe(TenantStatus::Active);
});

test('Tenant disabled state 工厂', function () {
    $t = Tenant::factory()->disabled()->create();
    expect($t->status)->toBe(TenantStatus::Disabled);
});
```

Run: `cd <root> && ./vendor/bin/pest app/Modules/Tenancy/Tests/TenantTest.php`
Expected: 3 tests passed.

> 如果 `RefreshDatabase` 报 `class not found`，修 `<root>/tests/TestCase.php` 加 `use Illuminate\Foundation\Testing\TestCase;` 并继承 Laravel 默认 TestCase（具体见 Laravel 13 默认 `tests/TestCase.php`）。

- [ ] **Step 4.9: Commit**

```bash
git add app/Modules/Tenancy bootstrap/providers.php
git commit -m "feat(tenancy): 新增 Tenant 实体（migration/model/factory/test）"
```

---

## Task 5: Tenancy 模块 - Store 实体（含 BelongsToTenant 跨租户隔离测试）

**Spec:** §3.2 + §8.1（最关键的不变量）

**Files:**
- Create: `<root>/app/Modules/Tenancy/Enums/StoreStatus.php`
- Create: `<root>/app/Modules/Tenancy/Models/Store.php`
- Create: `<root>/app/Modules/Tenancy/Database/Migrations/2026_05_08_000020_create_stores_table.php`
- Create: `<root>/app/Modules/Tenancy/Database/Factories/StoreFactory.php`
- Create: `<root>/app/Modules/Tenancy/Tests/StoreTest.php`

- [ ] **Step 5.1: StoreStatus enum**

```php
<?php
// <root>/app/Modules/Tenancy/Enums/StoreStatus.php
declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

enum StoreStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
```

- [ ] **Step 5.2: 迁移**

```php
<?php
// <root>/app/Modules/Tenancy/Database/Migrations/2026_05_08_000020_create_stores_table.php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->string('name', 120);
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
```

- [ ] **Step 5.3: Store 模型（挂 BelongsToTenant）**

```php
<?php
// <root>/app/Modules/Tenancy/Models/Store.php
declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Database\Factories\StoreFactory;
use App\Modules\Tenancy\Enums\StoreStatus;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Store extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'stores';
    protected $guarded = [];
    protected $casts = ['status' => StoreStatus::class];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected static function newFactory(): StoreFactory
    {
        return StoreFactory::new();
    }
}
```

- [ ] **Step 5.4: StoreFactory**

```php
<?php
// <root>/app/Modules/Tenancy/Database/Factories/StoreFactory.php
declare(strict_types=1);

namespace App\Modules\Tenancy\Database\Factories;

use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->city().'店',
            'status' => 'active',
        ];
    }
}
```

- [ ] **Step 5.5: TDD 跨租户隔离测试（最关键）**

写 `<root>/app/Modules/Tenancy/Tests/StoreTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    app(CurrentTenant::class)->set(null);
});

test('Store 工厂创建附带 tenant_id', function () {
    $s = Store::factory()->create();
    expect($s->tenant_id)->not->toBeNull();
    expect($s->tenant)->not->toBeNull();
});

test('CurrentTenant=null 时不过滤（登录前查询场景）', function () {
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    Store::factory()->create(['tenant_id' => $tA->id, 'name' => 'A 店']);
    Store::factory()->create(['tenant_id' => $tB->id, 'name' => 'B 店']);

    expect(Store::all())->toHaveCount(2);
});

test('BelongsToTenant 全局作用域只返回当前租户的 stores', function () {
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    Store::factory()->create(['tenant_id' => $tA->id, 'name' => 'A 店']);
    Store::factory()->create(['tenant_id' => $tB->id, 'name' => 'B 店']);

    app(CurrentTenant::class)->set($tA->id);

    $stores = Store::all();
    expect($stores)->toHaveCount(1);
    expect($stores->first()->name)->toBe('A 店');
});

test('BelongsToTenant 创建时自动注入 tenant_id', function () {
    $t = Tenant::factory()->create();
    app(CurrentTenant::class)->set($t->id);

    $s = Store::create(['name' => '示范店']);
    expect($s->tenant_id)->toBe($t->id);
});

test('CurrentTenant 未设置时创建抛 RuntimeException', function () {
    expect(fn () => Store::create(['name' => '应当失败店']))
        ->toThrow(RuntimeException::class);
});

test('withoutGlobalScopes 可绕过隔离', function () {
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    Store::factory()->create(['tenant_id' => $tA->id]);
    Store::factory()->create(['tenant_id' => $tB->id]);

    app(CurrentTenant::class)->set($tA->id);

    expect(Store::query()->withoutGlobalScopes()->count())->toBe(2);
});
```

Run: `cd <root> && ./vendor/bin/pest app/Modules/Tenancy/Tests/StoreTest.php`
Expected: 6 tests passed.

- [ ] **Step 5.6: Commit**

```bash
git add app/Modules/Tenancy
git commit -m "feat(tenancy): 新增 Store 实体并验证 BelongsToTenant 跨租户隔离"
```

---

## Task 6: Identity 模块 - User 实体

**Spec:** §3.3

**Files:**
- Create: `<root>/app/Modules/Identity/IdentityServiceProvider.php`
- Create: `<root>/app/Modules/Identity/Models/User.php`
- Create: `<root>/app/Modules/Identity/Database/Migrations/2026_05_08_000030_create_users_table.php`
- Create: `<root>/app/Modules/Identity/Database/Factories/UserFactory.php`
- Create: `<root>/app/Modules/Identity/Tests/UserTest.php`
- Modify: `<root>/bootstrap/providers.php`（追加 IdentityServiceProvider）
- Modify: `<root>/config/auth.php`（providers.users.model 指向新 User）

- [ ] **Step 6.1: 迁移**

```php
<?php
// <root>/app/Modules/Identity/Database/Migrations/2026_05_08_000030_create_users_table.php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name', 120);
            $table->string('phone', 20)->unique();
            $table->string('password');
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->boolean('is_platform_admin')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

- [ ] **Step 6.2: User 模型（不挂 BelongsToTenant —— 全局身份）**

```php
<?php
// <root>/app/Modules/Identity/Models/User.php
declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUlid;

    protected $table = 'users';
    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'last_login_at' => 'datetime',
        'is_platform_admin' => 'bool',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
```

- [ ] **Step 6.3: UserFactory**

```php
<?php
// <root>/app/Modules/Identity/Database/Factories/UserFactory.php
declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '13'.fake()->numerify('#########'),
            'password' => 'password',
            'status' => 'active',
            'is_platform_admin' => false,
        ];
    }

    public function platformAdmin(): self
    {
        return $this->state(['is_platform_admin' => true]);
    }
}
```

- [ ] **Step 6.4: IdentityServiceProvider**

```php
<?php
// <root>/app/Modules/Identity/IdentityServiceProvider.php
declare(strict_types=1);

namespace App\Modules\Identity;

use App\Support\ModuleServiceProvider;

class IdentityServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }
}
```

- [ ] **Step 6.5: 注册 Provider 与修 config/auth.php**

`<root>/bootstrap/providers.php`：

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Modules\Identity\IdentityServiceProvider::class,
    App\Modules\Tenancy\TenancyServiceProvider::class,
];
```

`<root>/config/auth.php` 的 `providers.users.model` 改为：

```php
'users' => [
    'driver' => 'eloquent',
    'model' => App\Modules\Identity\Models\User::class,
],
```

- [ ] **Step 6.6: 测试 User 创建 + ULID + password 自动 hash**

写 `<root>/app/Modules/Identity/Tests/UserTest.php`：

```php
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
```

Run: `cd <root> && ./vendor/bin/pest app/Modules/Identity/Tests/UserTest.php`
Expected: 5 passed.

- [ ] **Step 6.7: Commit**

```bash
git add app/Modules/Identity bootstrap/providers.php config/auth.php
git commit -m "feat(identity): 新增 User 实体与 Sanctum 接入"
```

---

## Task 7: Identity 模块 - Membership（含 withoutGlobalScopes 模式测试）

**Spec:** §3.4 + §5.3 注

**Files:**
- Create: `<root>/app/Modules/Identity/Models/Membership.php`
- Create: `<root>/app/Modules/Identity/Database/Migrations/2026_05_08_000040_create_memberships_table.php`
- Create: `<root>/app/Modules/Identity/Database/Factories/MembershipFactory.php`
- Create: `<root>/app/Modules/Identity/Tests/MembershipTest.php`

- [ ] **Step 7.1: 迁移**

```php
<?php
// <root>/app/Modules/Identity/Database/Migrations/2026_05_08_000040_create_memberships_table.php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('tenant_id', 26);
            $table->char('store_id', 26)->nullable();
            $table->enum('status', ['active', 'left'])->default('active');
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
            $table->index('user_id');
            $table->index(['tenant_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
```

- [ ] **Step 7.2: 模型**

```php
<?php
// <root>/app/Modules/Identity/Models/Membership.php
declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\MembershipFactory;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'memberships';
    protected $guarded = [];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected static function newFactory(): MembershipFactory
    {
        return MembershipFactory::new();
    }
}
```

- [ ] **Step 7.3: Factory**

```php
<?php
// <root>/app/Modules/Identity/Database/Factories/MembershipFactory.php
declare(strict_types=1);

namespace App\Modules\Identity\Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'store_id' => null,
            'status' => 'active',
            'joined_at' => now(),
        ];
    }

    public function left(): self
    {
        return $this->state(['status' => 'left']);
    }
}
```

- [ ] **Step 7.4: 测试跨租户查询模式**

写 `<root>/app/Modules/Identity/Tests/MembershipTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    app(CurrentTenant::class)->set(null);
});

test('user 关系返回正确 user', function () {
    $u = User::factory()->create();
    $rel = Membership::factory()->create(['user_id' => $u->id]);
    expect($rel->user->id)->toBe($u->id);
});

test('user.memberships 反向关系', function () {
    $u = User::factory()->create();
    Membership::factory()->count(2)->create(['user_id' => $u->id]);
    expect($u->memberships)->toHaveCount(2);
});

test('CurrentTenant 设置后默认 scope 仅返回当前租户的 rels', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tB->id]);

    app(CurrentTenant::class)->set($tA->id);
    expect(Membership::query()->where('user_id', $u->id)->count())->toBe(1);
});

test('withoutGlobalScopes 可跨租户列出 user 全部 memberships', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tB->id]);

    app(CurrentTenant::class)->set($tA->id);
    expect(
        Membership::query()->withoutGlobalScopes()->where('user_id', $u->id)->count()
    )->toBe(2);
});

test('store_id 可空表示租户级成员', function () {
    $rel = Membership::factory()->create(['store_id' => null]);
    expect($rel->store_id)->toBeNull();
});
```

Run: `cd <root> && ./vendor/bin/pest app/Modules/Identity/Tests/MembershipTest.php`
Expected: 5 passed.

- [ ] **Step 7.5: Commit**

```bash
git add app/Modules/Identity
git commit -m "feat(identity): 新增 Membership 关系表（验证 withoutGlobalScopes 跨租户查询模式）"
```

---

## Task 8: TenantMiddleware（含 §8.2 全部场景测试）

**Spec:** §3 中间件链 + §5 + §8.2

**Files:**
- Create: `<root>/app/Modules/Identity/Http/Middleware/TenantMiddleware.php`
- Modify: `<root>/bootstrap/app.php`（启用 alias）
- Create: `<root>/app/Modules/Identity/Routes/api.php`（占位 GET 路由用于测试中间件）
- Create: `<root>/app/Modules/Identity/Tests/TenantMiddlewareTest.php`

- [ ] **Step 8.1: 写 TenantMiddleware（基于 supermarket，改名为 Membership + Tenant）**

```php
<?php
// <root>/app/Modules/Identity/Http/Middleware/TenantMiddleware.php
declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\Membership;
use App\Support\Tenancy\CurrentMembership;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $tenantId = $request->header('X-Tenant-Id');
        if (! $tenantId || ! is_string($tenantId)) {
            return response()->json(['error' => 'X-Tenant-Id header required'], 403);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        if ($user->is_platform_admin) {
            app(CurrentTenant::class)->set($tenantId);
            app(CurrentMembership::class)->set(null);
            $request->attributes->set('current_membership', null);
            $request->attributes->set('is_platform_impersonation', true);

            return $next($request);
        }

        $membership = Membership::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return response()->json(['error' => 'no active membership'], 403);
        }

        app(CurrentTenant::class)->set($tenantId);
        app(CurrentMembership::class)->set($membership);
        $request->attributes->set('current_membership', $membership);
        $request->attributes->set('is_platform_impersonation', false);

        return $next($request);
    }
}
```

- [ ] **Step 8.2: 启用中间件别名（bootstrap/app.php）**

修 `<root>/bootstrap/app.php` 的 alias 段：

```php
$middleware->alias([
    'tenant' => \App\Modules\Identity\Http\Middleware\TenantMiddleware::class,
]);
```

注：`platform_admin` Task 12 再加。

- [ ] **Step 8.3: Identity Routes/api.php 占位路由**

```php
<?php
// <root>/app/Modules/Identity/Routes/api.php
declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('__tenant-probe', function () {
        return response()->json([
            'tenant_id' => app(\App\Support\Tenancy\CurrentTenant::class)->id(),
            'is_platform_impersonation' => request()->attributes->get('is_platform_impersonation'),
        ]);
    });
});
```

- [ ] **Step 8.4: 测试 §8.2 全场景**

写 `<root>/app/Modules/Identity/Tests/TenantMiddlewareTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

afterEach(function () {
    app(CurrentTenant::class)->set(null);
});

test('未认证返回 401', function () {
    $this->getJson('/api/__tenant-probe', ['X-Tenant-Id' => 'whatever'])
        ->assertStatus(401);
});

test('缺 X-Tenant-Id header 返回 403', function () {
    $u = User::factory()->create();
    Sanctum::actingAs($u);

    $this->getJson('/api/__tenant-probe')
        ->assertStatus(403)
        ->assertJson(['error' => 'X-Tenant-Id header required']);
});

test('普通 user 在该 tenant 没有 active membership 返回 403', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Sanctum::actingAs($u);

    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->getJson('/api/__tenant-probe')
        ->assertStatus(403)
        ->assertJson(['error' => 'no active membership']);
});

test('普通 user 有 active membership 通过', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create([
        'user_id' => $u->id, 'tenant_id' => $t->id, 'status' => 'active',
    ]);
    Sanctum::actingAs($u);

    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->getJson('/api/__tenant-probe')
        ->assertOk()
        ->assertJson([
            'tenant_id' => $t->id,
            'is_platform_impersonation' => false,
        ]);
});

test('platform admin 任意 X-Tenant-Id 通过且标 impersonation', function () {
    $u = User::factory()->platformAdmin()->create();
    $t = Tenant::factory()->create();
    Sanctum::actingAs($u);

    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->getJson('/api/__tenant-probe')
        ->assertOk()
        ->assertJson([
            'tenant_id' => $t->id,
            'is_platform_impersonation' => true,
        ]);
});

test('membership.status=left 返回 403', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->left()->create([
        'user_id' => $u->id, 'tenant_id' => $t->id,
    ]);
    Sanctum::actingAs($u);

    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->getJson('/api/__tenant-probe')
        ->assertStatus(403);
});
```

Run: `cd <root> && ./vendor/bin/pest app/Modules/Identity/Tests/TenantMiddlewareTest.php`
Expected: 6 passed.

- [ ] **Step 8.5: Commit**

```bash
git add app/Modules/Identity bootstrap/app.php
git commit -m "feat(identity): TenantMiddleware（X-Tenant-Id + 平台员工 impersonation）"
```

---

## Task 9: 登录 + 登出 API

**Spec:** §4 #1, #2 + §4.1（登录限流）+ §8.3

**Files:**
- Create: `<root>/app/Modules/Identity/Data/LoginData.php`
- Create: `<root>/app/Modules/Identity/Http/Requests/LoginRequest.php`
- Create: `<root>/app/Modules/Identity/Actions/LoginAction.php`
- Create: `<root>/app/Modules/Identity/Http/Controllers/AuthController.php`
- Modify: `<root>/app/Modules/Identity/Routes/api.php`（追加 login + logout 路由）
- Create: `<root>/app/Modules/Identity/Tests/AuthLoginTest.php`

- [ ] **Step 9.1: LoginData**

```php
<?php
// <root>/app/Modules/Identity/Data/LoginData.php
declare(strict_types=1);

namespace App\Modules\Identity\Data;

use Spatie\LaravelData\Data;

class LoginData extends Data
{
    public function __construct(
        public string $phone,
        public string $password,
    ) {}
}
```

- [ ] **Step 9.2: LoginRequest**

```php
<?php
// <root>/app/Modules/Identity/Http/Requests/LoginRequest.php
declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }
}
```

- [ ] **Step 9.3: LoginAction（去掉 supermarket 的 Activity::log）**

```php
<?php
// <root>/app/Modules/Identity/Actions/LoginAction.php
declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Data\LoginData;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    /**
     * @return array{token: string, user: User}
     */
    public function execute(LoginData $data): array
    {
        $user = User::query()
            ->where('phone', $data->phone)
            ->where('status', 'active')
            ->first();

        if (! $user || ! Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages(['phone' => '账号或密码错误']);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('api')->plainTextToken;

        return ['token' => $token, 'user' => $user];
    }
}
```

- [ ] **Step 9.4: AuthController（登录 + 登出）**

```php
<?php
// <root>/app/Modules/Identity/Http/Controllers/AuthController.php
declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\LoginAction;
use App\Modules\Identity\Data\LoginData;
use App\Modules\Identity\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $result = $action->execute(LoginData::from($request->validated()));
        $user = $result['user'];

        return response()->json([
            'token' => $result['token'],
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
                'is_platform_admin' => (bool) $user->is_platform_admin,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['ok' => true]);
    }
}
```

- [ ] **Step 9.5: 路由追加**

`<root>/app/Modules/Identity/Routes/api.php` 文件顶部插入（不在 auth:sanctum 块里）：

```php
Route::prefix('api/auth')->group(function () {
    Route::post('login', [\App\Modules\Identity\Http\Controllers\AuthController::class, 'login'])
        ->middleware('throttle:5,1');
});

Route::prefix('api/auth')->middleware('auth:sanctum')->group(function () {
    Route::post('logout', [\App\Modules\Identity\Http\Controllers\AuthController::class, 'logout']);
});
```

（保持原 `__tenant-probe` 占位路由）

- [ ] **Step 9.6: 测试**

写 `<root>/app/Modules/Identity/Tests/AuthLoginTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

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
    User::factory()->create(['phone' => '13800000002', 'password' => 'right']);

    $this->postJson('/api/auth/login', [
        'phone' => '13800000002',
        'password' => 'wrong',
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
    Sanctum::actingAs($u);

    $this->postJson('/api/auth/logout')->assertOk();
});
```

注：Pest 的 throttle 测试在 testing 环境通常会被 disable（看 supermarket 是否如此）。**不**单独写限流测试 —— 跑通基础 happy/error 路径即可，限流由生产环境自然校验。

Run: `cd <root> && ./vendor/bin/pest app/Modules/Identity/Tests/AuthLoginTest.php`
Expected: 6 passed.

- [ ] **Step 9.7: Commit**

```bash
git add app/Modules/Identity
git commit -m "feat(identity): 登录与登出 API（含 throttle 与防账号枚举）"
```

---

## Task 10: GET /api/me + GET /api/me/memberships

**Spec:** §4 #3, #4 + §5.3 注

**Files:**
- Create: `<root>/app/Modules/Identity/Http/Controllers/MeController.php`
- Modify: `<root>/app/Modules/Identity/Routes/api.php`
- Create: `<root>/app/Modules/Identity/Tests/MeTest.php`

- [ ] **Step 10.1: MeController**

```php
<?php
// <root>/app/Modules/Identity/Http/Controllers/MeController.php
declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'phone' => $user->phone,
            'name' => $user->name,
            'is_platform_admin' => (bool) $user->is_platform_admin,
        ]);
    }

    public function memberships(Request $request): JsonResponse
    {
        $user = $request->user();

        $memberships = Membership::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $tenantIds = $memberships->pluck('tenant_id')->all();
        $tenants = Tenant::query()
            ->whereIn('id', $tenantIds)
            ->get()
            ->keyBy('id');

        $list = $memberships->map(function (Membership $m) use ($tenants) {
            $tenant = $tenants->get($m->tenant_id);
            return [
                'tenant_id' => $m->tenant_id,
                'tenant_name' => $tenant?->name,
                'store_id' => $m->store_id,
                'joined_at' => optional($m->joined_at)->toIso8601String(),
            ];
        })->values();

        return response()->json(['memberships' => $list]);
    }
}
```

- [ ] **Step 10.2: 路由追加**

在 `<root>/app/Modules/Identity/Routes/api.php` 已有 `Route::prefix('api')->middleware('auth:sanctum')` 块（如不存在则新建）：

```php
Route::prefix('api')->middleware('auth:sanctum')->group(function () {
    Route::get('me', [\App\Modules\Identity\Http\Controllers\MeController::class, 'show']);
    Route::get('me/memberships', [\App\Modules\Identity\Http\Controllers\MeController::class, 'memberships']);
});
```

- [ ] **Step 10.3: 测试**

写 `<root>/app/Modules/Identity/Tests/MeTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('GET /api/me 未认证 401', function () {
    $this->getJson('/api/me')->assertStatus(401);
});

test('GET /api/me 返回主体', function () {
    $u = User::factory()->create();
    Sanctum::actingAs($u);

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJson([
            'id' => $u->id,
            'phone' => $u->phone,
            'is_platform_admin' => false,
        ]);
});

test('GET /api/me/memberships 列出所有 active rels（跨租户）', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create(['name' => 'A咖啡']);
    $tB = Tenant::factory()->create(['name' => 'B咖啡']);
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tB->id]);
    Membership::factory()->left()->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);

    Sanctum::actingAs($u);

    $resp = $this->getJson('/api/me/memberships')->assertOk();
    expect($resp->json('memberships'))->toHaveCount(2);
    $names = collect($resp->json('memberships'))->pluck('tenant_name')->sort()->values()->all();
    expect($names)->toBe(['A咖啡', 'B咖啡']);
});

test('GET /api/me/memberships 不返回 status=left 的', function () {
    $u = User::factory()->create();
    Membership::factory()->left()->create(['user_id' => $u->id]);
    Sanctum::actingAs($u);

    expect($this->getJson('/api/me/memberships')->json('memberships'))->toHaveCount(0);
});
```

Run: `cd <root> && ./vendor/bin/pest app/Modules/Identity/Tests/MeTest.php`
Expected: 4 passed.

- [ ] **Step 10.4: Commit**

```bash
git add app/Modules/Identity
git commit -m "feat(identity): GET /api/me 与 GET /api/me/memberships（跨租户绕过全局作用域）"
```

---

## Task 11: 租户域读端点（GET /api/tenants/current + GET /api/stores）

**Spec:** §4 #5, #6

**Files:**
- Create: `<root>/app/Modules/Tenancy/Http/Controllers/TenantController.php`
- Create: `<root>/app/Modules/Tenancy/Http/Controllers/StoreController.php`
- Create: `<root>/app/Modules/Tenancy/Routes/api.php`
- Create: `<root>/app/Modules/Tenancy/Tests/TenantReadEndpointsTest.php`

- [ ] **Step 11.1: TenantController**

```php
<?php
// <root>/app/Modules/Tenancy/Http/Controllers/TenantController.php
declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class TenantController extends Controller
{
    public function current(): JsonResponse
    {
        $id = app(CurrentTenant::class)->require();
        $tenant = Tenant::query()->whereKey($id)->firstOrFail();

        return response()->json([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'status' => $tenant->status->value,
        ]);
    }
}
```

- [ ] **Step 11.2: StoreController**

```php
<?php
// <root>/app/Modules/Tenancy/Http/Controllers/StoreController.php
declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class StoreController extends Controller
{
    public function index(): JsonResponse
    {
        // BelongsToTenant 全局作用域自动加 WHERE tenant_id = CurrentTenant
        $stores = Store::query()
            ->orderBy('created_at')
            ->get(['id', 'name', 'status']);

        return response()->json(['stores' => $stores]);
    }
}
```

- [ ] **Step 11.3: Tenancy Routes/api.php**

```php
<?php
// <root>/app/Modules/Tenancy/Routes/api.php
declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\StoreController;
use App\Modules\Tenancy\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('tenants/current', [TenantController::class, 'current']);
    Route::get('stores', [StoreController::class, 'index']);
});
```

- [ ] **Step 11.4: 测试跨租户隔离**

写 `<root>/app/Modules/Tenancy/Tests/TenantReadEndpointsTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('GET /api/tenants/current 返回 X-Tenant-Id 对应租户', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create(['name' => '示范咖啡']);
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    Sanctum::actingAs($u);

    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->getJson('/api/tenants/current')
        ->assertOk()
        ->assertJson(['id' => $t->id, 'name' => '示范咖啡']);
});

test('GET /api/stores 仅返回当前租户 stores（跨租户隔离）', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);

    Store::factory()->create(['tenant_id' => $tA->id, 'name' => 'A1 店']);
    Store::factory()->create(['tenant_id' => $tA->id, 'name' => 'A2 店']);
    Store::factory()->create(['tenant_id' => $tB->id, 'name' => 'B1 店']);

    Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $tA->id])->getJson('/api/stores');
    $resp->assertOk();
    expect($resp->json('stores'))->toHaveCount(2);
    $names = collect($resp->json('stores'))->pluck('name')->sort()->values()->all();
    expect($names)->toBe(['A1 店', 'A2 店']);
});

test('普通 user 用未绑定租户的 X-Tenant-Id 调 /api/stores 收 403', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);
    Store::factory()->create(['tenant_id' => $tB->id]);

    Sanctum::actingAs($u);
    $this->withHeaders(['X-Tenant-Id' => $tB->id])
        ->getJson('/api/stores')
        ->assertStatus(403);
});

test('platform admin 任意 X-Tenant-Id 都能列出该租户 stores', function () {
    $u = User::factory()->platformAdmin()->create();
    $tB = Tenant::factory()->create();
    Store::factory()->create(['tenant_id' => $tB->id, 'name' => 'B1 店']);

    Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $tB->id])->getJson('/api/stores');
    $resp->assertOk();
    expect($resp->json('stores'))->toHaveCount(1);
    expect($resp->json('stores.0.name'))->toBe('B1 店');
});
```

Run: `cd <root> && ./vendor/bin/pest app/Modules/Tenancy/Tests/TenantReadEndpointsTest.php`
Expected: 4 passed.

- [ ] **Step 11.5: Commit**

```bash
git add app/Modules/Tenancy
git commit -m "feat(tenancy): GET /api/tenants/current 与 GET /api/stores（跨租户隔离 + 平台 impersonation）"
```

---

## Task 12: PlatformAdminMiddleware + 平台员工创建端点

**Spec:** §3 中间件 + §4 #7~#9 + §8.4

**Files:**
- Create: `<root>/app/Modules/Identity/Http/Middleware/PlatformAdminMiddleware.php`
- Create: `<root>/app/Modules/Tenancy/Http/Controllers/Platform/PlatformTenantController.php`
- Create: `<root>/app/Modules/Tenancy/Http/Controllers/Platform/PlatformStoreController.php`
- Create: `<root>/app/Modules/Tenancy/Http/Controllers/Platform/PlatformUserController.php`
- Modify: `<root>/app/Modules/Tenancy/Routes/api.php`
- Modify: `<root>/bootstrap/app.php`（启用 platform_admin alias）
- Create: `<root>/app/Modules/Tenancy/Tests/PlatformEndpointsTest.php`

- [ ] **Step 12.1: PlatformAdminMiddleware**

```php
<?php
// <root>/app/Modules/Identity/Http/Middleware/PlatformAdminMiddleware.php
declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PlatformAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }
        if (! $user->is_platform_admin) {
            return response()->json(['error' => 'platform admin required'], 403);
        }
        return $next($request);
    }
}
```

- [ ] **Step 12.2: bootstrap/app.php 启用 platform_admin alias**

```php
$middleware->alias([
    'tenant' => \App\Modules\Identity\Http\Middleware\TenantMiddleware::class,
    'platform_admin' => \App\Modules\Identity\Http\Middleware\PlatformAdminMiddleware::class,
]);
```

- [ ] **Step 12.3: PlatformTenantController（创建租户）**

```php
<?php
// <root>/app/Modules/Tenancy/Http/Controllers/Platform/PlatformTenantController.php
declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers\Platform;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PlatformTenantController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $tenant = Tenant::create([
            'name' => $data['name'],
            'status' => 'active',
        ]);

        return response()->json([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'status' => $tenant->status->value,
        ], 201);
    }
}
```

- [ ] **Step 12.4: PlatformStoreController（在指定 tenant 下创建 store）**

```php
<?php
// <root>/app/Modules/Tenancy/Http/Controllers/Platform/PlatformStoreController.php
declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers\Platform;

use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PlatformStoreController extends Controller
{
    public function store(Request $request, string $tenantId): JsonResponse
    {
        $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        // 平台员工创建 store：必须显式指定 tenant_id（不依赖 CurrentTenant，因为本路由不挂 tenant 中间件）
        $store = Store::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'status' => 'active',
        ]);

        return response()->json([
            'id' => $store->id,
            'tenant_id' => $store->tenant_id,
            'name' => $store->name,
            'status' => $store->status->value,
        ], 201);
    }
}
```

> 注：BelongsToTenant trait 的 `creating` 钩子在 `tenant_id` 已被显式赋值时不会触发 require()，所以即使 CurrentTenant 为 null 也安全。

- [ ] **Step 12.5: PlatformUserController（创建用户 + 绑定）**

```php
<?php
// <root>/app/Modules/Tenancy/Http/Controllers/Platform/PlatformUserController.php
declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers\Platform;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class PlatformUserController extends Controller
{
    public function store(Request $request, string $tenantId): JsonResponse
    {
        $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:6'],
            'store_id' => ['nullable', 'string', 'size:26'],
        ]);

        if ($data['store_id'] ?? null) {
            $store = Store::query()->withoutGlobalScopes()
                ->where('id', $data['store_id'])
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();
        }

        return DB::transaction(function () use ($data, $tenant) {
            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'status' => 'active',
            ]);

            $rel = Membership::query()->withoutGlobalScopes()->create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'store_id' => $data['store_id'] ?? null,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'phone' => $user->phone,
                    'name' => $user->name,
                ],
                'membership' => [
                    'id' => $rel->id,
                    'tenant_id' => $rel->tenant_id,
                    'store_id' => $rel->store_id,
                ],
            ], 201);
        });
    }
}
```

- [ ] **Step 12.6: 路由追加（app/Modules/Tenancy/Routes/api.php 末尾）**

```php
Route::prefix('api/platform')->middleware(['auth:sanctum', 'platform_admin'])->group(function () {
    Route::post('tenants', [\App\Modules\Tenancy\Http\Controllers\Platform\PlatformTenantController::class, 'store']);
    Route::post('tenants/{tenantId}/stores', [\App\Modules\Tenancy\Http\Controllers\Platform\PlatformStoreController::class, 'store']);
    Route::post('tenants/{tenantId}/users', [\App\Modules\Tenancy\Http\Controllers\Platform\PlatformUserController::class, 'store']);
});
```

- [ ] **Step 12.7: 测试**

写 `<root>/app/Modules/Tenancy/Tests/PlatformEndpointsTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\Membership;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('非 platform admin 调 platform endpoint 返回 403', function () {
    $u = User::factory()->create();
    Sanctum::actingAs($u);

    $this->postJson('/api/platform/tenants', ['name' => '尝试'])
        ->assertStatus(403);
});

test('未登录调 platform endpoint 返回 401', function () {
    $this->postJson('/api/platform/tenants', ['name' => '尝试'])
        ->assertStatus(401);
});

test('platform admin 创建租户', function () {
    $admin = User::factory()->platformAdmin()->create();
    Sanctum::actingAs($admin);

    $resp = $this->postJson('/api/platform/tenants', ['name' => '九号咖啡'])
        ->assertStatus(201)
        ->assertJsonStructure(['id', 'name', 'status']);

    expect(Tenant::query()->where('name', '九号咖啡')->exists())->toBeTrue();
});

test('platform admin 创建 store', function () {
    $admin = User::factory()->platformAdmin()->create();
    $t = Tenant::factory()->create();
    Sanctum::actingAs($admin);

    $resp = $this->postJson("/api/platform/tenants/{$t->id}/stores", ['name' => '示范店']);
    $resp->assertStatus(201);

    expect(Store::query()->withoutGlobalScopes()->where('name', '示范店')->where('tenant_id', $t->id)->exists())
        ->toBeTrue();
});

test('platform admin 创建 user 并绑定 tenant 级 membership', function () {
    $admin = User::factory()->platformAdmin()->create();
    $t = Tenant::factory()->create();
    Sanctum::actingAs($admin);

    $resp = $this->postJson("/api/platform/tenants/{$t->id}/users", [
        'name' => '李店员',
        'phone' => '13800001111',
        'password' => 'secret123',
    ]);
    $resp->assertStatus(201);

    $newUser = User::where('phone', '13800001111')->firstOrFail();
    $rel = Membership::query()->withoutGlobalScopes()->where('user_id', $newUser->id)->first();
    expect($rel)->not->toBeNull();
    expect($rel->tenant_id)->toBe($t->id);
    expect($rel->store_id)->toBeNull();
    expect($rel->status)->toBe('active');
});

test('platform admin 创建 user 并绑定 store 级 membership', function () {
    $admin = User::factory()->platformAdmin()->create();
    $t = Tenant::factory()->create();
    $s = Store::factory()->create(['tenant_id' => $t->id]);
    Sanctum::actingAs($admin);

    $this->postJson("/api/platform/tenants/{$t->id}/users", [
        'name' => '张店长',
        'phone' => '13800002222',
        'password' => 'secret123',
        'store_id' => $s->id,
    ])->assertStatus(201);

    $rel = Membership::query()->withoutGlobalScopes()
        ->where('tenant_id', $t->id)
        ->whereNotNull('store_id')
        ->first();
    expect($rel->store_id)->toBe($s->id);
});

test('phone 重复创建 user 返回 422', function () {
    $admin = User::factory()->platformAdmin()->create();
    $t = Tenant::factory()->create();
    User::factory()->create(['phone' => '13899998888']);
    Sanctum::actingAs($admin);

    $this->postJson("/api/platform/tenants/{$t->id}/users", [
        'name' => 'X',
        'phone' => '13899998888',
        'password' => 'secret123',
    ])->assertStatus(422)->assertJsonValidationErrors(['phone']);
});
```

Run: `cd <root> && ./vendor/bin/pest app/Modules/Tenancy/Tests/PlatformEndpointsTest.php`
Expected: 7 passed.

- [ ] **Step 12.8: Commit**

```bash
git add app/Modules bootstrap/app.php
git commit -m "feat: PlatformAdminMiddleware 与平台后台 3 个创建端点（tenant/store/user）"
```

---

## Task 13: artisan coffee:bootstrap CLI（创建首个 platform admin）

**Spec:** §7

**Files:**
- Create: `<root>/app/Console/Commands/CoffeeBootstrap.php`
- Create: `<root>/tests/Feature/Console/CoffeeBootstrapTest.php`

- [ ] **Step 13.1: 写命令**

```php
<?php
// <root>/app/Console/Commands/CoffeeBootstrap.php
declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Identity\Models\User;
use Illuminate\Console\Command;

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
```

- [ ] **Step 13.2: 注册命令**

`<root>/app/Console/Commands/` 命令通常被 Laravel 13 自动发现（`Application::configure()->withCommands()`）。验证一次：

```bash
cd <root> && php artisan list | grep coffee
```
Expected: `coffee:bootstrap` 出现。如未出现，编辑 `<root>/bootstrap/app.php`，把 `withCommands` 加入：

```php
return Application::configure(...)
    ->withRouting(...)
    ->withMiddleware(...)
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withExceptions(...)->create();
```

- [ ] **Step 13.3: 测试**

写 `<root>/tests/Feature/Console/CoffeeBootstrapTest.php`：

```php
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
```

Run: `cd <root> && ./vendor/bin/pest tests/Feature/Console/CoffeeBootstrapTest.php`
Expected: 4 passed.

- [ ] **Step 13.4: Commit**

```bash
git add app/Console/Commands tests/Feature/Console
git commit -m "feat(console): 新增 coffee:bootstrap 命令创建首个 platform admin"
```

---

## Task 14: 前端 Inertia 最小骨架（Login + SelectTenant + Home）

**Spec:** §6

**Files:**
- Create: `<root>/resources/js/app.ts`
- Create: `<root>/resources/js/lib/axios.ts`
- Create: `<root>/resources/js/stores/auth.ts`
- Create: `<root>/resources/js/Layouts/AppLayout.vue`
- Create: `<root>/resources/js/Pages/Login.vue`
- Create: `<root>/resources/js/Pages/SelectTenant.vue`
- Create: `<root>/resources/js/Pages/Home.vue`
- Create: `<root>/resources/css/app.css`
- Create: `<root>/routes/web.php`（首页 / 登录 / 选租户路由 → Inertia render）
- Create: `<root>/resources/views/app.blade.php`（Inertia entry blade）
- Modify: `<root>/composer.json` 已含 inertia
- Modify: `<root>/bootstrap/app.php`（在 web 中间件组加 HandleInertiaRequests）
- Create: `<root>/app/Http/Middleware/HandleInertiaRequests.php`

> 由于本任务涉及前端工程，**TDD 不强制**（Pest 不便测 Vue）。验收用 `npm run build` + `vue-tsc --noEmit` 通过即可，外加手动登录走通 happy path。

- [ ] **Step 14.1: 安装 Inertia + Vue 适配（之前的 `composer install`/`npm install` 已装）**

确认：

```bash
cd <root>
grep '"@inertiajs/vue3"' package.json
grep '"inertiajs/inertia-laravel"' composer.json
```
Expected: 都存在。

- [ ] **Step 14.2: HandleInertiaRequests middleware**

```php
<?php
// <root>/app/Http/Middleware/HandleInertiaRequests.php
declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'appName' => config('app.name'),
        ]);
    }
}
```

注册到 `<root>/bootstrap/app.php` 的 web 中间件组：

```php
$middleware->web(append: [
    \App\Http\Middleware\HandleInertiaRequests::class,
]);
```

- [ ] **Step 14.3: Blade root view**

`<root>/resources/views/app.blade.php`：

```blade
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body class="antialiased bg-stone-50">
    @inertia
</body>
</html>
```

- [ ] **Step 14.4: app.ts 入口**

```ts
// <root>/resources/js/app.ts
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import ElementPlus from 'element-plus';
import 'element-plus/dist/index.css';
import './lib/axios';

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
    return pages[`./Pages/${name}.vue`];
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(createPinia())
      .use(ElementPlus)
      .mount(el);
  },
});
```

- [ ] **Step 14.5: axios 全局拦截器**

```ts
// <root>/resources/js/lib/axios.ts
import axios from 'axios';

axios.defaults.baseURL = window.location.origin;

axios.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  const tenantId = localStorage.getItem('tenant_id');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  if (tenantId) config.headers['X-Tenant-Id'] = tenantId;
  return config;
});

export default axios;
```

- [ ] **Step 14.6: Pinia auth store**

```ts
// <root>/resources/js/stores/auth.ts
import { defineStore } from 'pinia';

interface User {
  id: string;
  phone: string;
  name: string;
  is_platform_admin: boolean;
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    tenantId: localStorage.getItem('tenant_id'),
  }),
  actions: {
    setToken(token: string) {
      localStorage.setItem('token', token);
    },
    setTenant(id: string) {
      this.tenantId = id;
      localStorage.setItem('tenant_id', id);
    },
    clearTenant() {
      this.tenantId = null;
      localStorage.removeItem('tenant_id');
    },
    logout() {
      localStorage.removeItem('token');
      localStorage.removeItem('tenant_id');
      this.user = null;
      this.tenantId = null;
    },
  },
});
```

- [ ] **Step 14.7: Login.vue**

```vue
<!-- <root>/resources/js/Pages/Login.vue -->
<template>
  <div class="min-h-screen flex items-center justify-center bg-stone-50">
    <el-card class="w-96">
      <h1 class="text-2xl font-semibold mb-6 text-stone-800">咖啡 SaaS 登录</h1>
      <el-form @submit.prevent="login">
        <el-form-item label="手机号">
          <el-input v-model="phone" placeholder="13800000000" />
        </el-form-item>
        <el-form-item label="密码">
          <el-input v-model="password" type="password" show-password />
        </el-form-item>
        <el-button type="primary" native-type="submit" :loading="busy" class="w-full">
          登录
        </el-button>
        <div v-if="error" class="text-red-500 mt-3 text-sm">{{ error }}</div>
      </el-form>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import axios from '../lib/axios';
import { useAuthStore } from '../stores/auth';
import { router } from '@inertiajs/vue3';

const auth = useAuthStore();
const phone = ref('');
const password = ref('');
const busy = ref(false);
const error = ref('');

async function login() {
  busy.value = true;
  error.value = '';
  try {
    const { data } = await axios.post('/api/auth/login', {
      phone: phone.value, password: password.value,
    });
    auth.setToken(data.token);
    auth.user = data.user;
    router.visit('/select-tenant');
  } catch (e: any) {
    error.value = e?.response?.data?.errors?.phone?.[0] ?? '登录失败';
  } finally {
    busy.value = false;
  }
}
</script>
```

- [ ] **Step 14.8: SelectTenant.vue**

```vue
<!-- <root>/resources/js/Pages/SelectTenant.vue -->
<template>
  <div class="min-h-screen p-8 bg-stone-50">
    <h1 class="text-2xl font-semibold mb-6 text-stone-800">选择租户</h1>
    <div v-if="loading" class="text-stone-500">加载中…</div>
    <div v-else-if="memberships.length === 0" class="text-stone-500">
      你尚未被绑定到任何租户，请联系平台管理员。
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <el-card v-for="m in memberships" :key="m.tenant_id" shadow="hover"
               class="cursor-pointer hover:border-amber-600" @click="enter(m.tenant_id)">
        <div class="text-lg font-medium text-stone-800">{{ m.tenant_name }}</div>
        <div class="text-xs text-stone-500 mt-1">
          {{ m.store_id ? '门店级成员' : '租户级成员' }}
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from '../lib/axios';
import { useAuthStore } from '../stores/auth';
import { router } from '@inertiajs/vue3';

interface Membership {
  tenant_id: string; tenant_name: string; store_id: string | null;
}

const auth = useAuthStore();
const memberships = ref<Membership[]>([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/me/memberships');
    memberships.value = data.memberships;
  } finally {
    loading.value = false;
  }
});

function enter(id: string) {
  auth.setTenant(id);
  router.visit('/');
}
</script>
```

- [ ] **Step 14.9: Home.vue + AppLayout.vue**

```vue
<!-- <root>/resources/js/Layouts/AppLayout.vue -->
<template>
  <div>
    <header class="bg-amber-700 text-white px-6 py-3 flex items-center justify-between">
      <div class="font-semibold">{{ appName ?? 'Coffee' }}</div>
      <div class="text-sm flex items-center gap-4">
        <span v-if="auth.tenantId">当前租户：{{ auth.tenantId }}</span>
        <el-button size="small" @click="switchTenant">切换租户</el-button>
        <el-button size="small" @click="logout">登出</el-button>
      </div>
    </header>
    <main class="p-8">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import axios from '../lib/axios';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const page = usePage();
const appName = (page.props as any).appName;

function switchTenant() {
  auth.clearTenant();
  router.visit('/select-tenant');
}

async function logout() {
  try {
    await axios.post('/api/auth/logout');
  } catch (_) { /* ignore */ }
  auth.logout();
  router.visit('/login');
}
</script>
```

```vue
<!-- <root>/resources/js/Pages/Home.vue -->
<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto">
      <h1 class="text-3xl font-semibold text-stone-800 mb-4">欢迎</h1>
      <p class="text-stone-600">
        当前租户 ID：<code>{{ auth.tenantId ?? '未选择' }}</code>
      </p>
      <p v-if="!auth.tenantId" class="text-amber-700 mt-2">
        请先 <a href="/select-tenant" class="underline">选择租户</a>
      </p>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '../Layouts/AppLayout.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
</script>
```

- [ ] **Step 14.10: routes/web.php**

```php
<?php
// <root>/routes/web.php
declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/login', fn () => Inertia::render('Login'));
Route::get('/select-tenant', fn () => Inertia::render('SelectTenant'));
Route::get('/', fn () => Inertia::render('Home'));
```

- [ ] **Step 14.11: resources/css/app.css**

```css
@import 'tailwindcss';
```

- [ ] **Step 14.12: Build 验证**

```bash
cd <root>
npm run build
```
Expected: 构建成功，输出 `public/build/manifest.json` 等。

```bash
cd <root>
npx vue-tsc --noEmit
```
Expected: 无类型错误。

- [ ] **Step 14.13: 启动 dev server 手动验证**

```bash
cd <root>
php artisan migrate:fresh
php artisan coffee:bootstrap --phone=13800000000 --password=secret123
# 用 curl 创建 tenant + 给 13800000001 创建账号
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login -H 'Content-Type: application/json' \
  -d '{"phone":"13800000000","password":"secret123"}' | jq -r .token)
TID=$(curl -s -X POST http://localhost:8000/api/platform/tenants -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -d '{"name":"九号咖啡"}' | jq -r .id)
curl -s -X POST http://localhost:8000/api/platform/tenants/$TID/users \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"name":"店员","phone":"13800000001","password":"secret123"}'

# 然后启动两端
composer dev    # 启动 server + queue + pail + vite （来自 supermarket scripts.dev）
# 浏览器访问 http://localhost:8000/login，用 13800000001 / secret123 登录 → 选择租户 → 看到 Home
```

注意：这步是**手动验收**。subagent 的工作止于 `npm run build` + `vue-tsc` 通过，并把上述操作记录在提交信息里。

- [ ] **Step 14.14: Commit**

```bash
git add app/Http resources routes/web.php bootstrap/app.php
git commit -m "feat(frontend): Inertia + Vue 3 最小前端骨架（登录 / 选租户 / 主页）"
```

---

## Task 15: 全量验收 + README

**Files:**
- Create: `<root>/README.md`

- [ ] **Step 15.1: 跑全部 Pest 测试**

```bash
cd <root>
./vendor/bin/pest
```
Expected: 全绿，覆盖以下任务的测试：
- Task 2 SmokeTest（1）
- Task 3 CurrentTenantTest（4）
- Task 4 TenantTest（3）
- Task 5 StoreTest（6）
- Task 6 UserTest（5）
- Task 7 MembershipTest（5）
- Task 8 TenantMiddlewareTest（6）
- Task 9 AuthLoginTest（6）
- Task 10 MeTest（4）
- Task 11 TenantReadEndpointsTest（4）
- Task 12 PlatformEndpointsTest（7）
- Task 13 CoffeeBootstrapTest（4）
合计 **55 tests passed**（数量可能因实现微调浮动 ±2）。

如有红：诊断、修复、提 commit。

- [ ] **Step 15.2: Pint 格式校验**

```bash
cd <root>
./vendor/bin/pint --test
```
Expected: 0 issues。如有则 `./vendor/bin/pint` 修复并 commit。

- [ ] **Step 15.3: 前端类型与构建检查**

```bash
cd <root>
npx vue-tsc --noEmit
npm run build
```
Expected: 全部通过。

- [ ] **Step 15.4: 写 README.md**

```markdown
# Coffee — 咖啡连锁 SaaS（首期：身份骨架）

> 基于 supermarket 框架移植，按 base.md §1~§2 / §6.1 实现的最小可跑多租户身份骨架。

## 快速开始

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# 创建首个平台员工
php artisan coffee:bootstrap --phone=13800000000 --password=secret123

# 启动开发环境（后端 + 队列 + 日志 + 前端）
composer dev
```

## 浏览器验证 happy path

1. 访问 http://localhost:8000/login，用平台员工 13800000000 登录。
2. 平台员工通过 curl/Postman 调 `/api/platform/tenants` 创建租户、`/api/platform/tenants/{id}/users` 创建员工。
3. 用员工账号登录，进入 `/select-tenant` 选择租户，进入主页。

## 测试

```bash
./vendor/bin/pest
./vendor/bin/pint --test
npx vue-tsc --noEmit
```

## 文档

- 设计：`docs/superpowers/specs/2026-05-07-identity-skeleton-design.md`
- 实施计划：`docs/superpowers/plans/2026-05-08-identity-skeleton-implementation.md`
- base.md：`docs/base.md`

## 后续路线（不在首期）

- RBAC（base.md §3、§6.2）
- 数据权限范围 ALL/STORE/SELF/CUSTOM（§5）
- 审计日志（§8.4）
- 套餐/计费（§1.1）
- 咖啡业务模块（订单、商品、库存、营销、会员）
```

- [ ] **Step 15.5: Commit + 终结提交**

```bash
git add README.md
git commit -m "docs: 添加 README 与首期验收说明"
```

- [ ] **Step 15.6: 最终自检**

确认：
- [ ] `./vendor/bin/pest` 全绿
- [ ] `./vendor/bin/pint --test` 零差异
- [ ] `npx vue-tsc --noEmit` 零错误
- [ ] `npm run build` 成功
- [ ] `php artisan list | grep coffee` 显示 `coffee:bootstrap`
- [ ] `php artisan route:list --path=api` 显示 9 个 API 端点

如全部通过，本计划完成。

---

## 依赖图（任务之间的顺序约束）

```text
Task 0 (git config 检查)
  └─→ Task 1 (Laravel 骨架) ── necessary，所有任务的根
        └─→ Task 2 (Sanctum migration)
              └─→ Task 3 (Support 层)
                    ├─→ Task 4 (Tenant)
                    │     └─→ Task 5 (Store)
                    │           └─→ Task 6 (User)
                    │                 └─→ Task 7 (Membership)
                    │                       └─→ Task 8 (TenantMiddleware)
                    │                             └─→ Task 9 (Login)
                    │                                   └─→ Task 10 (Me)
                    │                                         └─→ Task 11 (Tenancy 读端点)
                    │                                               └─→ Task 12 (Platform 端点)
                    │                                                     └─→ Task 13 (CLI)
                    │                                                           └─→ Task 14 (前端)
                    │                                                                 └─→ Task 15 (验收)
```

由于强依赖链，**所有任务必须串行执行**，不适合并行 subagent。subagent-driven 仍然有用（每个 task 一个 fresh context + spec/quality 双审）。

---

## 自审

- ✅ Spec 覆盖：§1~§11 每节都有对应 task；非目标都明确不实现。
- ✅ 无 placeholder：所有步骤都有实际代码或命令。
- ✅ 类型一致性：Task 3 定义 `CurrentTenant`、Task 5 起调用 `app(CurrentTenant::class)`，签名一致。`Membership` 类名 Task 3、7、8、10、12 一致。
- ✅ TDD 节奏：每个数据/逻辑任务先写测试或与代码同步；前端任务的 TDD 弱化（说明合理）。
