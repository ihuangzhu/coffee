# RBAC 模块实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在 Coffee 项目（Identity / Tenancy 骨架之上）落地 minimal RBAC 模块：4 表 + 12 enum 权限码 + 双中间件 + 13 端点 + 6 内置预设角色。

**Architecture:** 新建 `app/Modules/Authorization/` 模块（与 Identity / Tenancy 平级）。权限以 PHP enum 单一来源，角色权限存 JSON 数组。`roles` / `platform_roles` 双表硬隔离 platform 与商户域权限码（INV-14）。`user_role_bindings` 独立表支持一人多角色，权限取并集进 request attribute。

**Tech Stack:** PHP 8.3+ / Laravel 13 / Pest 4 / SQLite `:memory:`（测试）/ MySQL（生产）

**Spec 来源:** `docs/superpowers/specs/2026-05-08-rbac-design.md`

---

## 前置约定

- **工作目录**：`/mnt/d/Projects/Huang/coffee`，下文 `<root>`
- **分支**：直接在 `master` 上推进（项目早期，单分支）
- **提交规范**：中文 conventional commits，禁用 `--no-verify`
- **测试**：`./vendor/bin/pest`，每个任务结束前必须**全套**绿（`./vendor/bin/pest` 不带 path）
- **TDD 节奏**：写失败测试 → 跑确认 RED → 写最小实现 → 跑确认 GREEN → 提交。每个 Task 一个 commit。
- **Spec 章节标记**：每个 Task 标 `Spec §N.N`，便于回溯

**关键资产位置（已存在）**

```
app/Support/Eloquent/{HasUlid,BelongsToTenant,TenantScope}.php
app/Support/Tenancy/{CurrentTenant,CurrentMembership}.php
app/Support/Exceptions/BusinessException.php
app/Support/ModuleServiceProvider.php   -- 自动加载模块 Routes + Migrations
app/Modules/Identity/Models/{User,Membership}.php
app/Modules/Identity/Http/Middleware/TenantMiddleware.php
app/Modules/Tenancy/Models/{Tenant,Store}.php
app/Modules/Tenancy/Routes/api.php       -- 现有 /api/platform/* 端点在此
bootstrap/app.php                        -- 中间件别名注册位置
bootstrap/providers.php                  -- ServiceProvider 注册位置
```

**关键缺位（本计划会补）**

- `lang/zh-CN/errors.php` — 不存在；Task 22 创建
- `bootstrap/app.php` 的 `withExceptions` 当前为空；本计划假设 BusinessException 仅在控制器内 throw，渲染逻辑由 Task 22 一并补上

---

## Task 1: Permission enum + ValidPermissionsRule

**Spec §3, §4.1**

**Files:**
- Create: `app/Modules/Authorization/Enums/Permission.php`
- Create: `app/Modules/Authorization/Rules/ValidPermissionsRule.php`
- Create: `app/Modules/Authorization/Tests/Unit/PermissionEnumClosureTest.php`
- Create: `app/Modules/Authorization/Tests/Unit/ValidPermissionsRuleTest.php`

- [ ] **Step 1.1: 写 enum 闭包性失败测试**

`app/Modules/Authorization/Tests/Unit/PermissionEnumClosureTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\Permission;

test('Permission enum 所有 case 均不带 platform. 前缀（INV-A）', function () {
    foreach (Permission::cases() as $case) {
        expect($case->value)->not->toStartWith('platform.');
    }
});

test('Permission enum 含首期 6 个权限码', function () {
    $values = array_map(fn ($c) => $c->value, Permission::cases());
    expect($values)->toContain('roles.read', 'roles.manage', 'users.read', 'users.assign-role', 'tenant.read', 'stores.read');
});
```

- [ ] **Step 1.2: 跑确认 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Unit/PermissionEnumClosureTest.php 2>&1 | tail -5
```
Expected: FAIL with "Class Permission not found".

- [ ] **Step 1.3: 实现 Permission enum**

`app/Modules/Authorization/Enums/Permission.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Enums;

/**
 * 商户域权限（INV-A：所有 case 不可有 platform. 前缀）。
 *
 * 单一来源：写入 roles.permissions 时仅接受这里枚举值的 string。
 * 后续 Goods / Order / Inventory 等模块各自 PR 在此追加 case。
 */
enum Permission: string
{
    case RolesRead       = 'roles.read';
    case RolesManage     = 'roles.manage';
    case UsersRead       = 'users.read';
    case UsersAssignRole = 'users.assign-role';
    case TenantRead      = 'tenant.read';
    case StoresRead      = 'stores.read';
}
```

- [ ] **Step 1.4: 跑确认 enum 测试 GREEN**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Unit/PermissionEnumClosureTest.php 2>&1 | tail -5
```
Expected: PASS 2 tests.

- [ ] **Step 1.5: 写 ValidPermissionsRule 失败测试**

`app/Modules/Authorization/Tests/Unit/ValidPermissionsRuleTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Rules\ValidPermissionsRule;
use Illuminate\Support\Facades\Validator;

function validateWithRule(mixed $value): array
{
    return Validator::make(
        ['perms' => $value],
        ['perms' => ['array', new ValidPermissionsRule]]
    )->errors()->all();
}

test('合法 Permission code 列表通过', function () {
    expect(validateWithRule(['roles.read', 'users.read']))->toBe([]);
});

test('空数组通过', function () {
    expect(validateWithRule([]))->toBe([]);
});

test('拒绝拼写错误的 code', function () {
    $errors = validateWithRule(['roles.unknown']);
    expect($errors)->not->toBeEmpty();
});

test('拒绝任何 platform.* 前缀（INV-B 双向校验）', function () {
    $errors = validateWithRule(['platform.tenants.manage']);
    expect($errors)->not->toBeEmpty();
});

test('拒绝混合（一条 platform.* + 一条合法）', function () {
    $errors = validateWithRule(['roles.read', 'platform.impersonate.full']);
    expect($errors)->not->toBeEmpty();
});
```

- [ ] **Step 1.6: 跑确认 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Unit/ValidPermissionsRuleTest.php 2>&1 | tail -5
```
Expected: FAIL with "Class ValidPermissionsRule not found".

- [ ] **Step 1.7: 实现 ValidPermissionsRule**

`app/Modules/Authorization/Rules/ValidPermissionsRule.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Rules;

use App\Modules\Authorization\Enums\Permission;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * roles.permissions 写入校验：
 *   - 必须是数组
 *   - 每个元素必须是 Permission enum 合法值
 *   - 显式拒绝任何带 platform. 前缀的 code（INV-B 双向校验）
 */
class ValidPermissionsRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('权限列表必须为数组');
            return;
        }

        $allowed = array_map(fn (Permission $p) => $p->value, Permission::cases());

        foreach ($value as $code) {
            if (! is_string($code)) {
                $fail("权限码必须是字符串：{$code}");
                return;
            }
            if (str_starts_with($code, 'platform.')) {
                $fail("不允许在商户角色中写入平台权限码：{$code}");
                return;
            }
            if (! in_array($code, $allowed, strict: true)) {
                $fail("非法权限码：{$code}");
                return;
            }
        }
    }
}
```

- [ ] **Step 1.8: 跑确认全部 GREEN**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Unit/ 2>&1 | tail -5
```
Expected: PASS 7 tests (2 enum + 5 rule).

- [ ] **Step 1.9: 跑全套测试确认无回归**

```bash
./vendor/bin/pest 2>&1 | tail -3
```
Expected: 全套 PASS（应为 59 + 7 = 66 测试）。

- [ ] **Step 1.10: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/Enums/Permission.php \
  app/Modules/Authorization/Rules/ValidPermissionsRule.php \
  app/Modules/Authorization/Tests/Unit/PermissionEnumClosureTest.php \
  app/Modules/Authorization/Tests/Unit/ValidPermissionsRuleTest.php

git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): Permission enum + ValidPermissionsRule

INV-A：所有 case 不带 platform. 前缀
INV-B：写入校验拒绝跨域 platform.* 权限码
首期 6 个商户域权限码"
```

---

## Task 2: PlatformPermission enum + ValidPlatformPermissionsRule

**Spec §3.2, §4.2**

**Files:**
- Create: `app/Modules/Authorization/Enums/PlatformPermission.php`
- Create: `app/Modules/Authorization/Rules/ValidPlatformPermissionsRule.php`
- Create: `app/Modules/Authorization/Tests/Unit/PlatformPermissionEnumClosureTest.php`
- Create: `app/Modules/Authorization/Tests/Unit/ValidPlatformPermissionsRuleTest.php`

- [ ] **Step 2.1: 写失败测试（同 Task 1 风格，对称写）**

`app/Modules/Authorization/Tests/Unit/PlatformPermissionEnumClosureTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Enums\PlatformPermission;

test('PlatformPermission enum 所有 case 必须 platform. 前缀（INV-A）', function () {
    foreach (PlatformPermission::cases() as $case) {
        expect($case->value)->toStartWith('platform.');
    }
});

test('含首期 6 个平台权限码', function () {
    $values = array_map(fn ($c) => $c->value, PlatformPermission::cases());
    expect($values)->toContain(
        'platform.impersonate.full',
        'platform.impersonate.read-only',
        'platform.tenants.manage',
        'platform.stores.manage',
        'platform.users.manage',
        'platform.roles.manage',
    );
});
```

`app/Modules/Authorization/Tests/Unit/ValidPlatformPermissionsRuleTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Rules\ValidPlatformPermissionsRule;
use Illuminate\Support\Facades\Validator;

function validateWithPlatformRule(mixed $value): array
{
    return Validator::make(
        ['perms' => $value],
        ['perms' => ['array', new ValidPlatformPermissionsRule]]
    )->errors()->all();
}

test('合法 PlatformPermission code 列表通过', function () {
    expect(validateWithPlatformRule(['platform.tenants.manage', 'platform.impersonate.full']))->toBe([]);
});

test('空数组通过', function () {
    expect(validateWithPlatformRule([]))->toBe([]);
});

test('拒绝商户域权限码（不带 platform. 前缀）', function () {
    $errors = validateWithPlatformRule(['roles.manage']);
    expect($errors)->not->toBeEmpty();
});

test('拒绝拼写错误的 platform.xxx 码', function () {
    $errors = validateWithPlatformRule(['platform.unknown.thing']);
    expect($errors)->not->toBeEmpty();
});
```

- [ ] **Step 2.2: 跑确认 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Unit/PlatformPermissionEnumClosureTest.php 2>&1 | tail -5
./vendor/bin/pest app/Modules/Authorization/Tests/Unit/ValidPlatformPermissionsRuleTest.php 2>&1 | tail -5
```
Expected: FAIL "Class not found".

- [ ] **Step 2.3: 实现 PlatformPermission enum**

`app/Modules/Authorization/Enums/PlatformPermission.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Enums;

/**
 * 平台域权限（INV-A：所有 case 必须 platform. 前缀）。
 *
 * 与 Permission 严格不互通（INV-14）：写入 platform_roles.permissions
 * 时仅接受这里枚举值；写入 roles.permissions 时禁止任何 platform.* 前缀。
 *
 * 双 enum 结构强制隔离平台与商户能力空间。
 */
enum PlatformPermission: string
{
    case ImpersonateFull     = 'platform.impersonate.full';
    case ImpersonateReadOnly = 'platform.impersonate.read-only';
    case TenantsManage       = 'platform.tenants.manage';
    case StoresManage        = 'platform.stores.manage';
    case UsersManage         = 'platform.users.manage';
    case PlatformRolesManage = 'platform.roles.manage';
}
```

- [ ] **Step 2.4: 实现 ValidPlatformPermissionsRule**

`app/Modules/Authorization/Rules/ValidPlatformPermissionsRule.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Rules;

use App\Modules\Authorization\Enums\PlatformPermission;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPlatformPermissionsRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('权限列表必须为数组');
            return;
        }

        $allowed = array_map(fn (PlatformPermission $p) => $p->value, PlatformPermission::cases());

        foreach ($value as $code) {
            if (! is_string($code)) {
                $fail("权限码必须是字符串");
                return;
            }
            if (! str_starts_with($code, 'platform.')) {
                $fail("平台角色权限码必须 platform. 前缀：{$code}");
                return;
            }
            if (! in_array($code, $allowed, strict: true)) {
                $fail("非法平台权限码：{$code}");
                return;
            }
        }
    }
}
```

- [ ] **Step 2.5: 跑确认 GREEN + 全套**

```bash
./vendor/bin/pest 2>&1 | tail -3
```
Expected: 全套 PASS（66 + 6 = 72）。

- [ ] **Step 2.6: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): PlatformPermission enum + ValidPlatformPermissionsRule

INV-A 对称：所有 case 必须 platform. 前缀
INV-B 对称：写入校验拒绝商户域权限码
首期 6 个平台权限码（含双级 impersonation）"
```

---

## Task 3: roles 表 + Role model + RoleFactory

**Spec §3.1, §4.3**

**Files:**
- Create: `app/Modules/Authorization/AuthorizationServiceProvider.php`
- Create: `app/Modules/Authorization/Database/Migrations/2026_05_08_000050_create_roles_table.php`
- Create: `app/Modules/Authorization/Models/Role.php`
- Create: `app/Modules/Authorization/Database/Factories/RoleFactory.php`
- Create: `app/Modules/Authorization/Tests/Unit/RoleModelTest.php`
- Modify: `bootstrap/providers.php` — 追加 `AuthorizationServiceProvider::class`

- [ ] **Step 3.1: 创建 AuthorizationServiceProvider（与 Identity / Tenancy 同款）**

`app/Modules/Authorization/AuthorizationServiceProvider.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization;

use App\Support\ModuleServiceProvider;

/**
 * Authorization 模块服务提供者。
 *
 * 由 ModuleServiceProvider 基类自动加载 Routes/api.php + Database/Migrations。
 */
class AuthorizationServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }
}
```

- [ ] **Step 3.2: 注册到 bootstrap/providers.php**

修改 `bootstrap/providers.php`，把 `AuthorizationServiceProvider::class` 追加到 return 数组：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\AuthorizationServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Tenancy\TenancyServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    TenancyServiceProvider::class,
    AuthorizationServiceProvider::class,
];
```

- [ ] **Step 3.3: 写 Role model 失败测试**

`app/Modules/Authorization/Tests/Unit/RoleModelTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Role 工厂可创建 tenant 级角色', function () {
    $t = Tenant::factory()->create();
    $r = Role::factory()->create([
        'tenant_id' => $t->id,
        'scope' => 'tenant',
        'permissions' => ['roles.read', 'users.read'],
    ]);

    expect($r->id)->toBeString()->toHaveLength(26);
    expect($r->permissions)->toBe(['roles.read', 'users.read']);
    expect($r->scope)->toBe('tenant');
    expect($r->is_system)->toBeFalse();
});

test('Role 工厂可创建全局模板（tenant_id NULL）', function () {
    $r = Role::factory()->systemTemplate()->create([
        'code' => 'TenantAdmin',
        'permissions' => ['roles.manage'],
    ]);

    expect($r->tenant_id)->toBeNull();
    expect($r->is_system)->toBeTrue();
});

test('permissions JSON cast 工作', function () {
    $r = Role::factory()->create(['permissions' => ['roles.read']]);
    $reloaded = Role::find($r->id);
    expect($reloaded->permissions)->toBe(['roles.read']);
});
```

- [ ] **Step 3.4: 跑确认 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Unit/RoleModelTest.php 2>&1 | tail -5
```
Expected: FAIL "Table not found / class not found".

- [ ] **Step 3.5: 写 migration**

`app/Modules/Authorization/Database/Migrations/2026_05_08_000050_create_roles_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26)->nullable();
            $table->string('name', 120);
            $table->string('code', 60);
            $table->enum('scope', ['tenant', 'store']);
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index('code');
            // 不加 UNIQUE(tenant_id, code) —— MySQL InnoDB 中 UNIQUE 视 NULL ≠ NULL，
            // 多份 (NULL, 'TenantAdmin') 不被拒；改由 Service 层 + seeder firstOrCreate 保证唯一
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
```

- [ ] **Step 3.6: 写 Role model**

`app/Modules/Authorization/Models/Role.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Models;

use App\Modules\Authorization\Database\Factories\RoleFactory;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 商户域角色（spec §3.1）。
 *
 * tenant_id NULL = 系统预置全局模板；非 NULL = 商户专属。
 * **不挂 BelongsToTenant trait** —— 全局模板需在 tenant_id IS NULL 下跨租户可见，
 * 由 Service 层显式做 (tenant_id = X OR tenant_id IS NULL) 过滤。
 */
class Role extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'roles';

    protected $guarded = [];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'bool',
    ];

    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }
}
```

- [ ] **Step 3.7: 写 RoleFactory**

`app/Modules/Authorization/Database/Factories/RoleFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Database\Factories;

use App\Modules\Authorization\Models\Role;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->jobTitle(),
            'code' => 'CustomRole_'.fake()->bothify('??##'),
            'scope' => 'tenant',
            'permissions' => [],
            'is_system' => false,
        ];
    }

    public function systemTemplate(): self
    {
        return $this->state([
            'tenant_id' => null,
            'is_system' => true,
        ]);
    }

    public function storeScope(): self
    {
        return $this->state(['scope' => 'store']);
    }
}
```

- [ ] **Step 3.8: 跑确认 GREEN**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Unit/RoleModelTest.php 2>&1 | tail -5
./vendor/bin/pest 2>&1 | tail -3
```
Expected: 全套 PASS（72 + 3 = 75）。

- [ ] **Step 3.9: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/ bootstrap/providers.php
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): roles 表 + Role model + AuthorizationServiceProvider

- AuthorizationServiceProvider 接入 ModuleServiceProvider 自动加载机制
- roles 表：tenant_id 可空（NULL=全局模板），permissions JSON 数组
- 不加 UNIQUE(tenant_id, code) 因 MySQL NULL 行为；由 Service 层兜底
- Role model 不挂 BelongsToTenant（全局模板需跨租户可见）"
```

---

## Task 4: platform_roles 表 + PlatformRole model

**Spec §3.2, §4.3**

**Files:**
- Create: `app/Modules/Authorization/Database/Migrations/2026_05_08_000051_create_platform_roles_table.php`
- Create: `app/Modules/Authorization/Models/PlatformRole.php`
- Create: `app/Modules/Authorization/Database/Factories/PlatformRoleFactory.php`
- Create: `app/Modules/Authorization/Tests/Unit/PlatformRoleModelTest.php`

- [ ] **Step 4.1: 写失败测试**

`app/Modules/Authorization/Tests/Unit/PlatformRoleModelTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('PlatformRole 工厂可创建', function () {
    $r = PlatformRole::factory()->create([
        'code' => 'PlatformOps',
        'permissions' => ['platform.tenants.manage'],
    ]);

    expect($r->id)->toBeString()->toHaveLength(26);
    expect($r->permissions)->toBe(['platform.tenants.manage']);
    expect($r->is_system)->toBeFalse();
});

test('code UNIQUE 约束生效', function () {
    PlatformRole::factory()->create(['code' => 'DuplicateCode']);
    expect(fn () => PlatformRole::factory()->create(['code' => 'DuplicateCode']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 4.2: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Unit/PlatformRoleModelTest.php 2>&1 | tail -5
```

- [ ] **Step 4.3: 写 migration / model / factory**

migration `2026_05_08_000051_create_platform_roles_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_roles', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name', 120);
            $table->string('code', 60)->unique();
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_roles');
    }
};
```

`app/Modules/Authorization/Models/PlatformRole.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Models;

use App\Modules\Authorization\Database\Factories\PlatformRoleFactory;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 平台域角色（spec §3.2）。INV-14：与 Role 严格不互通。
 * 仅 users.is_platform_admin=true 的用户可持有 platform_role_id。
 */
class PlatformRole extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'platform_roles';

    protected $guarded = [];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'bool',
    ];

    protected static function newFactory(): PlatformRoleFactory
    {
        return PlatformRoleFactory::new();
    }
}
```

`app/Modules/Authorization/Database/Factories/PlatformRoleFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Database\Factories;

use App\Modules\Authorization\Models\PlatformRole;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformRoleFactory extends Factory
{
    protected $model = PlatformRole::class;

    public function definition(): array
    {
        return [
            'name' => fake()->jobTitle(),
            'code' => 'CustomPlatformRole_'.fake()->bothify('??##'),
            'permissions' => [],
            'is_system' => false,
        ];
    }
}
```

- [ ] **Step 4.4: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 4.5: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): platform_roles 表 + PlatformRole model

INV-14：与 roles 表严格不互通
code UNIQUE 全局唯一（无 tenant_id 维度）"
```

---

## Task 5: alter users 加 platform_role_id 列 + User.platformRole 关系

**Spec §3.4**

**Files:**
- Create: `app/Modules/Authorization/Database/Migrations/2026_05_08_000052_alter_users_add_platform_role_id.php`
- Modify: `app/Modules/Identity/Models/User.php` — 新增 `platformRole()` 方法

- [ ] **Step 5.1: 写测试**

把测试加到 `app/Modules/Authorization/Tests/Unit/PlatformRoleModelTest.php`：

```php
test('User.platformRole 关系返回正确 PlatformRole', function () {
    $role = \App\Modules\Authorization\Models\PlatformRole::factory()->create();
    $user = \App\Modules\Identity\Models\User::factory()->platformAdmin()->create([
        'platform_role_id' => $role->id,
    ]);
    expect($user->platformRole->id)->toBe($role->id);
});

test('User.platformRole 默认 null', function () {
    $user = \App\Modules\Identity\Models\User::factory()->create();
    expect($user->platformRole)->toBeNull();
});
```

- [ ] **Step 5.2: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Unit/PlatformRoleModelTest.php 2>&1 | tail -5
```

- [ ] **Step 5.3: 写 migration**

`app/Modules/Authorization/Database/Migrations/2026_05_08_000052_alter_users_add_platform_role_id.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('platform_role_id', 26)->nullable()->after('is_platform_admin');
            $table->foreign('platform_role_id')->references('id')->on('platform_roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['platform_role_id']);
            $table->dropColumn('platform_role_id');
        });
    }
};
```

- [ ] **Step 5.4: 修改 User model 加 platformRole 关系**

`app/Modules/Identity/Models/User.php`，在 `memberships()` 方法之前加：

```php
public function platformRole(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(\App\Modules\Authorization\Models\PlatformRole::class, 'platform_role_id');
}
```

- [ ] **Step 5.5: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 5.6: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/ app/Modules/Identity/Models/User.php
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): users.platform_role_id + User.platformRole 关系

平台员工的角色 1:1 关联（首期不做多角色，平台员工总数极少）"
```

---

## Task 6: user_role_bindings 表 + UserRoleBinding model + User.roleBindings 关系

**Spec §3.3**

**Files:**
- Create: `app/Modules/Authorization/Database/Migrations/2026_05_08_000053_create_user_role_bindings_table.php`
- Create: `app/Modules/Authorization/Models/UserRoleBinding.php`
- Create: `app/Modules/Authorization/Database/Factories/UserRoleBindingFactory.php`
- Create: `app/Modules/Authorization/Tests/Unit/UserRoleBindingModelTest.php`
- Modify: `app/Modules/Identity/Models/User.php` — 加 `roleBindings()` 关系

- [ ] **Step 6.1: 写失败测试**

`app/Modules/Authorization/Tests/Unit/UserRoleBindingModelTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(fn () => app(CurrentTenant::class)->set(null));

test('UserRoleBinding 工厂可创建 tenant 级绑定', function () {
    $b = UserRoleBinding::factory()->create();
    expect($b->id)->toHaveLength(26);
    expect($b->status)->toBe('active');
    expect($b->store_id)->toBeNull();
});

test('store 级绑定带 store_id', function () {
    $b = UserRoleBinding::factory()->storeScope()->create();
    expect($b->store_id)->not->toBeNull();
});

test('User.roleBindings 反向关系（跨租户全部 active）', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    UserRoleBinding::factory()->count(2)->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'tenant_id' => $tB->id]);

    // 跨租户：默认 BelongsToTenant 全局 scope 会过滤；显式 withoutGlobalScopes
    expect($u->roleBindings()->withoutGlobalScopes()->count())->toBe(3);
});

test('CurrentTenant scope 默认过滤当前租户绑定', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    UserRoleBinding::factory()->count(2)->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'tenant_id' => $tB->id]);

    app(CurrentTenant::class)->set($tA->id);
    expect(UserRoleBinding::query()->where('user_id', $u->id)->count())->toBe(2);
});

test('revoked 状态保留', function () {
    $b = UserRoleBinding::factory()->revoked()->create();
    expect($b->status)->toBe('revoked');
});
```

- [ ] **Step 6.2: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Unit/UserRoleBindingModelTest.php 2>&1 | tail -5
```

- [ ] **Step 6.3: 写 migration**

`app/Modules/Authorization/Database/Migrations/2026_05_08_000053_create_user_role_bindings_table.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role_bindings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('role_id', 26);
            $table->char('tenant_id', 26);
            $table->char('store_id', 26)->nullable();
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->char('granted_by', 26)->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();

            $table->index(['user_id', 'tenant_id']);
            $table->index(['tenant_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_bindings');
    }
};
```

- [ ] **Step 6.4: 写 model**

`app/Modules/Authorization/Models/UserRoleBinding.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Models;

use App\Modules\Authorization\Database\Factories\UserRoleBindingFactory;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Eloquent\BelongsToTenant;
use App\Support\Eloquent\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 用户-角色绑定（spec §3.3）。
 *
 * - 挂 BelongsToTenant：默认查询自动按 tenant_id 过滤
 * - 跨租户场景（如 me/permissions 收集所有租户绑定）必须显式 withoutGlobalScopes()
 * - status=revoked 留痕，权限解析时必须显式 status=active
 * - INV-F：scope=tenant 角色 ↔ store_id NULL；scope=store ↔ store_id 非 NULL（应用层校验）
 */
class UserRoleBinding extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlid;

    protected $table = 'user_role_bindings';

    protected $guarded = [];

    protected $casts = [
        'granted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected static function newFactory(): UserRoleBindingFactory
    {
        return UserRoleBindingFactory::new();
    }
}
```

- [ ] **Step 6.5: 写 factory**

`app/Modules/Authorization/Database/Factories/UserRoleBindingFactory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Database\Factories;

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserRoleBindingFactory extends Factory
{
    protected $model = UserRoleBinding::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'user_id' => User::factory(),
            'role_id' => Role::factory()->create(['tenant_id' => $tenant->id])->id,
            'tenant_id' => $tenant->id,
            'store_id' => null,
            'status' => 'active',
            'granted_at' => now(),
        ];
    }

    public function storeScope(): self
    {
        return $this->state(function (array $attrs) {
            $store = Store::factory()->create(['tenant_id' => $attrs['tenant_id']]);
            return ['store_id' => $store->id];
        });
    }

    public function revoked(): self
    {
        return $this->state(['status' => 'revoked']);
    }
}
```

- [ ] **Step 6.6: 修改 User model 加 roleBindings 关系**

`app/Modules/Identity/Models/User.php`，紧贴 `platformRole()` 之后加：

```php
public function roleBindings(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Modules\Authorization\Models\UserRoleBinding::class);
}
```

- [ ] **Step 6.7: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```
Expected: 全套 PASS。

- [ ] **Step 6.8: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/ app/Modules/Identity/Models/User.php
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): user_role_bindings 表 + UserRoleBinding model

挂 BelongsToTenant：默认查询自动租户隔离
跨租户列表场景（me/permissions）必须显式 withoutGlobalScopes()
revoked 状态留痕，活跃权限解析必须 status=active 过滤
User.roleBindings 反向关系"
```

---

## Task 7: 内置预设角色 seed migration

**Spec §4.3**

**Files:**
- Create: `app/Modules/Authorization/Database/Migrations/2026_05_08_000054_seed_system_roles.php`
- Create: `app/Modules/Authorization/Tests/Unit/SystemRolesSeedTest.php`

> **为什么用 migration 而非 DB seeder**：is_system 角色是骨架（部署即必备），不是 dev fixture。任何环境跑 `migrate:fresh` 都要有；DB seeder 是 `db:seed` 才跑的开发数据。

- [ ] **Step 7.1: 写测试**

`app/Modules/Authorization/Tests/Unit/SystemRolesSeedTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Authorization\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('migrate 后 roles 有 3 个全局模板（is_system=true）', function () {
    $roles = Role::query()->where('is_system', true)->whereNull('tenant_id')->get();
    $codes = $roles->pluck('code')->sort()->values()->all();
    expect($codes)->toBe(['StoreClerk', 'StoreManager', 'TenantAdmin']);
});

test('TenantAdmin 含全部 6 个商户域权限', function () {
    $r = Role::query()->where('code', 'TenantAdmin')->whereNull('tenant_id')->firstOrFail();
    expect($r->permissions)->toEqualCanonicalizing([
        'roles.read', 'roles.manage', 'users.read', 'users.assign-role', 'tenant.read', 'stores.read',
    ]);
    expect($r->scope)->toBe('tenant');
});

test('StoreManager 含读类权限，scope=store', function () {
    $r = Role::query()->where('code', 'StoreManager')->whereNull('tenant_id')->firstOrFail();
    expect($r->permissions)->toEqualCanonicalizing(['roles.read', 'users.read', 'tenant.read', 'stores.read']);
    expect($r->scope)->toBe('store');
});

test('platform_roles 有 3 个 is_system 角色', function () {
    $roles = PlatformRole::query()->where('is_system', true)->get();
    $codes = $roles->pluck('code')->sort()->values()->all();
    expect($codes)->toBe(['PlatformOps', 'PlatformReadOnly', 'PlatformSuperAdmin']);
});

test('PlatformSuperAdmin 含全部 6 个平台权限', function () {
    $r = PlatformRole::query()->where('code', 'PlatformSuperAdmin')->firstOrFail();
    expect($r->permissions)->toEqualCanonicalizing([
        'platform.impersonate.full', 'platform.impersonate.read-only',
        'platform.tenants.manage', 'platform.stores.manage',
        'platform.users.manage', 'platform.roles.manage',
    ]);
});

test('PlatformReadOnly 仅含 ImpersonateReadOnly', function () {
    $r = PlatformRole::query()->where('code', 'PlatformReadOnly')->firstOrFail();
    expect($r->permissions)->toBe(['platform.impersonate.read-only']);
});
```

- [ ] **Step 7.2: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Unit/SystemRolesSeedTest.php 2>&1 | tail -5
```

- [ ] **Step 7.3: 写 seed migration（用 Eloquent 直接写入，免裸 SQL）**

`app/Modules/Authorization/Database/Migrations/2026_05_08_000054_seed_system_roles.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Authorization\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $merchantPerms = [
            'roles.read', 'roles.manage', 'users.read', 'users.assign-role', 'tenant.read', 'stores.read',
        ];

        // 商户全局模板（tenant_id=NULL）
        Role::query()->create([
            'tenant_id' => null,
            'name' => '商户管理员',
            'code' => 'TenantAdmin',
            'scope' => 'tenant',
            'permissions' => $merchantPerms,
            'is_system' => true,
        ]);
        Role::query()->create([
            'tenant_id' => null,
            'name' => '门店店长',
            'code' => 'StoreManager',
            'scope' => 'store',
            'permissions' => ['roles.read', 'users.read', 'tenant.read', 'stores.read'],
            'is_system' => true,
        ]);
        Role::query()->create([
            'tenant_id' => null,
            'name' => '门店店员',
            'code' => 'StoreClerk',
            'scope' => 'store',
            'permissions' => ['tenant.read', 'stores.read'],
            'is_system' => true,
        ]);

        // 平台角色
        $platformPerms = [
            'platform.impersonate.full', 'platform.impersonate.read-only',
            'platform.tenants.manage', 'platform.stores.manage',
            'platform.users.manage', 'platform.roles.manage',
        ];
        PlatformRole::query()->create([
            'name' => '平台超级管理员',
            'code' => 'PlatformSuperAdmin',
            'permissions' => $platformPerms,
            'is_system' => true,
        ]);
        PlatformRole::query()->create([
            'name' => '平台运营',
            'code' => 'PlatformOps',
            'permissions' => [
                'platform.impersonate.full', 'platform.tenants.manage',
                'platform.stores.manage', 'platform.users.manage',
            ],
            'is_system' => true,
        ]);
        PlatformRole::query()->create([
            'name' => '平台只读',
            'code' => 'PlatformReadOnly',
            'permissions' => ['platform.impersonate.read-only'],
            'is_system' => true,
        ]);
    }

    public function down(): void
    {
        Role::query()->where('is_system', true)->whereNull('tenant_id')
            ->whereIn('code', ['TenantAdmin', 'StoreManager', 'StoreClerk'])
            ->delete();
        PlatformRole::query()->where('is_system', true)
            ->whereIn('code', ['PlatformSuperAdmin', 'PlatformOps', 'PlatformReadOnly'])
            ->delete();
    }
};
```

- [ ] **Step 7.4: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 7.5: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): seed 6 个内置预设角色（is_system=true）

3 个商户全局模板（tenant_id NULL）+ 3 个平台角色
用 migration 而非 db:seed —— 骨架数据部署即必备"
```

---

## Task 8: PermissionResolver service（取并集逻辑）

**Spec §5.1**

**Files:**
- Create: `app/Modules/Authorization/Services/PermissionResolver.php`
- Create: `app/Modules/Authorization/Tests/Unit/PermissionResolverTest.php`

- [ ] **Step 8.1: 写失败测试**

`app/Modules/Authorization/Tests/Unit/PermissionResolverTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Authorization\Services\PermissionResolver;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('单个 binding 的权限正确返回', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    $r = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['roles.read', 'users.read']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r->id, 'tenant_id' => $t->id]);

    $resolver = new PermissionResolver();
    $perms = $resolver->resolveForUserInTenant($u, $t->id);
    expect($perms)->toEqualCanonicalizing(['roles.read', 'users.read']);
});

test('多个 active binding 的权限取并集去重', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    $r1 = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['roles.read', 'users.read']]);
    $r2 = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['users.read', 'stores.read']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r1->id, 'tenant_id' => $t->id]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r2->id, 'tenant_id' => $t->id]);

    $perms = (new PermissionResolver())->resolveForUserInTenant($u, $t->id);
    expect($perms)->toEqualCanonicalizing(['roles.read', 'users.read', 'stores.read']);
});

test('revoked binding 不计入', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    $rActive = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['roles.read']]);
    $rRevoked = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['users.read']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $rActive->id, 'tenant_id' => $t->id]);
    UserRoleBinding::factory()->revoked()->create(['user_id' => $u->id, 'role_id' => $rRevoked->id, 'tenant_id' => $t->id]);

    $perms = (new PermissionResolver())->resolveForUserInTenant($u, $t->id);
    expect($perms)->toBe(['roles.read']);
});

test('其它租户的 binding 不计入', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    $rA = Role::factory()->create(['tenant_id' => $tA->id, 'permissions' => ['roles.read']]);
    $rB = Role::factory()->create(['tenant_id' => $tB->id, 'permissions' => ['users.read']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $rA->id, 'tenant_id' => $tA->id]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $rB->id, 'tenant_id' => $tB->id]);

    expect((new PermissionResolver())->resolveForUserInTenant($u, $tA->id))->toBe(['roles.read']);
    expect((new PermissionResolver())->resolveForUserInTenant($u, $tB->id))->toBe(['users.read']);
});

test('user 在该租户无任何 active binding 返回空数组', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    expect((new PermissionResolver())->resolveForUserInTenant($u, $t->id))->toBe([]);
});

test('resolveBindingsForUserInTenant 返回 active 绑定集合（不计 revoked）', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    UserRoleBinding::factory()->count(2)->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    UserRoleBinding::factory()->revoked()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);

    expect((new PermissionResolver())->resolveBindingsForUserInTenant($u, $t->id))->toHaveCount(2);
});
```

- [ ] **Step 8.2: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Unit/PermissionResolverTest.php 2>&1 | tail -5
```

- [ ] **Step 8.3: 实现 PermissionResolver**

`app/Modules/Authorization/Services/PermissionResolver.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Services;

use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Collection;

/**
 * 权限解析器（spec §5.1）。
 *
 * 给定 (user, tenant_id)，返回该用户在该租户下所有 active binding 关联角色 permissions 的并集。
 *
 * 关键点：
 *   - withoutGlobalScopes()：BelongsToTenant 全局 scope 在跨请求场景可能残留旧 tenant；显式去除
 *   - status='active'：revoked 不计入
 *   - JSON 数组解析依赖 Role.permissions 的 array cast
 */
class PermissionResolver
{
    /** @return string[] 权限码扁平列表（去重） */
    public function resolveForUserInTenant(User $user, string $tenantId): array
    {
        $bindings = $this->resolveBindingsForUserInTenant($user, $tenantId);

        $perms = [];
        foreach ($bindings as $b) {
            foreach ($b->role?->permissions ?? [] as $code) {
                $perms[$code] = true;
            }
        }

        $list = array_keys($perms);
        sort($list);
        return $list;
    }

    /** @return Collection<UserRoleBinding> active 绑定集合（含 role 关联预加载） */
    public function resolveBindingsForUserInTenant(User $user, string $tenantId): Collection
    {
        return UserRoleBinding::query()
            ->withoutGlobalScopes()
            ->with('role')
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();
    }
}
```

- [ ] **Step 8.4: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 8.5: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): PermissionResolver 服务（取并集 + 跨租户隔离）

- 单 binding / 多 binding 并集 / revoked 忽略 / 跨租户隔离 4 个关键路径
- withoutGlobalScopes 处理 BelongsToTenant 残留 scope 问题"
```

---

## Task 9: TenantMiddleware 增量改造（注入 platform_role / bindings / effective_permissions）

**Spec §5.1**

**Files:**
- Modify: `app/Modules/Identity/Http/Middleware/TenantMiddleware.php`
- Modify: `app/Modules/Identity/Tests/TenantMiddlewareTest.php`（追加测试）

- [ ] **Step 9.1: 在现有测试文件追加新行为测试**

在 `app/Modules/Identity/Tests/TenantMiddlewareTest.php` 文件**底部**追加：

```php
test('普通员工通过后注入 effective_permissions 取并集', function () {
    $u = \App\Modules\Identity\Models\User::factory()->create();
    $t = \App\Modules\Tenancy\Models\Tenant::factory()->create();
    \App\Modules\Identity\Models\Membership::factory()->create([
        'user_id' => $u->id, 'tenant_id' => $t->id, 'status' => 'active',
    ]);
    $r1 = \App\Modules\Authorization\Models\Role::factory()->create([
        'tenant_id' => $t->id, 'permissions' => ['roles.read', 'users.read'],
    ]);
    $r2 = \App\Modules\Authorization\Models\Role::factory()->create([
        'tenant_id' => $t->id, 'permissions' => ['users.read', 'stores.read'],
    ]);
    \App\Modules\Authorization\Models\UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $r1->id, 'tenant_id' => $t->id,
    ]);
    \App\Modules\Authorization\Models\UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $r2->id, 'tenant_id' => $t->id,
    ]);

    \Laravel\Sanctum\Sanctum::actingAs($u);

    // probe 端点回响 attributes（仅 testing 环境注册）
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__tenant-probe');
    $resp->assertOk();

    // probe 端点已存在但还没回响 effective_permissions —— 我们改 probe 端点把它也带上（在下一步）
});

test('platform admin impersonation 注入 platform_role', function () {
    $admin = \App\Modules\Identity\Models\User::factory()->platformAdmin()->create();
    $pr = \App\Modules\Authorization\Models\PlatformRole::factory()->create([
        'permissions' => ['platform.impersonate.full'],
    ]);
    $admin->update(['platform_role_id' => $pr->id]);
    $t = \App\Modules\Tenancy\Models\Tenant::factory()->create();

    \Laravel\Sanctum\Sanctum::actingAs($admin);
    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->getJson('/api/__tenant-probe')
        ->assertOk()
        ->assertJson(['is_platform_impersonation' => true]);
});
```

> 注：上述测试现在仅断言现有 probe 端点能跑过；下一步要把 probe 端点的回响内容扩展，断言新注入的 attributes。

- [ ] **Step 9.2: 修改 `/api/__tenant-probe` 端点回响新 attributes**

修改 `app/Modules/Identity/Routes/api.php` 中 probe 端点：

```php
// 仅在 testing 环境注册：避免生产/线上暴露内部探针端点
if (app()->environment('testing')) {
    Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::get('__tenant-probe', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'tenant_id' => app(\App\Support\Tenancy\CurrentTenant::class)->id(),
                'is_platform_impersonation' => $request->attributes->get('is_platform_impersonation'),
                'platform_role_code' => $request->attributes->get('platform_role')?->code,
                'effective_permissions' => $request->attributes->get('effective_permissions'),
                'role_bindings_count' => $request->attributes->get('current_role_bindings')?->count(),
            ]);
        });
    });
}
```

- [ ] **Step 9.3: 在 Step 9.1 的两个测试里追加更具体的 assertion**

把 Step 9.1 的两个测试改为：

```php
test('普通员工通过后注入 effective_permissions 取并集', function () {
    $u = \App\Modules\Identity\Models\User::factory()->create();
    $t = \App\Modules\Tenancy\Models\Tenant::factory()->create();
    \App\Modules\Identity\Models\Membership::factory()->create([
        'user_id' => $u->id, 'tenant_id' => $t->id, 'status' => 'active',
    ]);
    $r1 = \App\Modules\Authorization\Models\Role::factory()->create([
        'tenant_id' => $t->id, 'permissions' => ['roles.read', 'users.read'],
    ]);
    $r2 = \App\Modules\Authorization\Models\Role::factory()->create([
        'tenant_id' => $t->id, 'permissions' => ['users.read', 'stores.read'],
    ]);
    \App\Modules\Authorization\Models\UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $r1->id, 'tenant_id' => $t->id,
    ]);
    \App\Modules\Authorization\Models\UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $r2->id, 'tenant_id' => $t->id,
    ]);

    \Laravel\Sanctum\Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__tenant-probe');

    $resp->assertOk();
    expect($resp->json('effective_permissions'))->toEqualCanonicalizing(['roles.read', 'users.read', 'stores.read']);
    expect($resp->json('role_bindings_count'))->toBe(2);
    expect($resp->json('is_platform_impersonation'))->toBeFalse();
});

test('platform admin impersonation 注入 platform_role', function () {
    $admin = \App\Modules\Identity\Models\User::factory()->platformAdmin()->create();
    $pr = \App\Modules\Authorization\Models\PlatformRole::factory()->create([
        'code' => 'TestSuperAdmin',
        'permissions' => ['platform.impersonate.full'],
    ]);
    $admin->update(['platform_role_id' => $pr->id]);
    $t = \App\Modules\Tenancy\Models\Tenant::factory()->create();

    \Laravel\Sanctum\Sanctum::actingAs($admin);
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__tenant-probe');

    $resp->assertOk();
    expect($resp->json('is_platform_impersonation'))->toBeTrue();
    expect($resp->json('platform_role_code'))->toBe('TestSuperAdmin');
    expect($resp->json('effective_permissions'))->toBeNull();
});
```

- [ ] **Step 9.4: 跑 RED 确认新断言失败**

```bash
./vendor/bin/pest app/Modules/Identity/Tests/TenantMiddlewareTest.php 2>&1 | tail -10
```
Expected: 两个新测试 FAIL。

- [ ] **Step 9.5: 修改 TenantMiddleware**

`app/Modules/Identity/Http/Middleware/TenantMiddleware.php` 完整改写：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Authorization\Services\PermissionResolver;
use App\Modules\Identity\Models\Membership;
use App\Support\Tenancy\CurrentMembership;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;

/**
 * 租户解析中间件（spec §5.1）。
 *
 * 职责：
 *   1. 强制 X-Tenant-Id header 存在（否则 403）
 *   2. 平台员工（is_platform_admin=true）→ 直接信任 header；platform_role 注入；
 *      attributes：is_platform_impersonation=true / platform_role / 其余为 null
 *   3. 普通员工 → 必须有 status=active membership；解析 effective_permissions
 *      attributes：is_platform_impersonation=false / current_role_bindings / effective_permissions
 *   4. 通过后，CurrentTenant / CurrentMembership / request attributes 全部就绪
 */
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
            $request->attributes->set('platform_role', $user->platformRole);
            $request->attributes->set('current_role_bindings', null);
            $request->attributes->set('effective_permissions', null);

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

        $resolver = app(PermissionResolver::class);
        $bindings = $resolver->resolveBindingsForUserInTenant($user, $tenantId);
        $effective = $resolver->resolveForUserInTenant($user, $tenantId);

        $request->attributes->set('current_membership', $membership);
        $request->attributes->set('is_platform_impersonation', false);
        $request->attributes->set('platform_role', null);
        $request->attributes->set('current_role_bindings', $bindings);
        $request->attributes->set('effective_permissions', $effective);

        return $next($request);
    }
}
```

- [ ] **Step 9.6: 跑 GREEN（含全套）**

```bash
./vendor/bin/pest 2>&1 | tail -3
```
Expected: 全套 PASS。

- [ ] **Step 9.7: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Identity/ app/Modules/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): TenantMiddleware 注入 platform_role / role_bindings / effective_permissions

INV-C：is_platform_impersonation=true 时不消费 role_bindings
INV-D：is_platform_impersonation=false 时不消费 platform_role
现 testing-only probe 端点回响 5 个 attributes 便于断言"
```

---

## Task 10: 中间件别名注册（permission / platform_permission） + Routes/api.php 骨架

**Spec §5.2, §5.3**

**Files:**
- Modify: `bootstrap/app.php` — 加 alias
- Create: `app/Modules/Authorization/Routes/api.php`（空骨架，后续 Task 加端点）

- [ ] **Step 10.1: 修改 bootstrap/app.php 注册别名**

```php
<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Authorization\Http\Middleware\PermissionMiddleware;
use App\Modules\Authorization\Http\Middleware\PlatformPermissionMiddleware;
use App\Modules\Identity\Http\Middleware\PlatformAdminMiddleware;
use App\Modules\Identity\Http\Middleware\TenantMiddleware;
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
            'permission' => PermissionMiddleware::class,
            'platform_permission' => PlatformPermissionMiddleware::class,
        ]);
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

> 此时 `PermissionMiddleware` / `PlatformPermissionMiddleware` 类还未创建；下两个 Task 才写。`bootstrap/app.php` 的 alias 注册不引发即时类加载（直到匹配路由），所以这一步先放着不会跑挂。

- [ ] **Step 10.2: 写 Routes/api.php 空骨架**

`app/Modules/Authorization/Routes/api.php`：

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 * Authorization 模块路由。
 *
 * 后续 Task 在此追加：
 *   - 商户域端点（/api/roles, /api/users, /api/users/{id}/role-bindings, /api/role-bindings/{id}, /api/me/permissions）
 *   - 平台域端点（/api/platform/roles, /api/platform/users/{id}/platform-role）
 */

Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    // 端点在 Task 13~ 加入
});
```

- [ ] **Step 10.3: 不跑测试，直接确认 artisan 能 boot**

```bash
php artisan --version 2>&1 | tail -3
```
Expected: `Laravel Framework 13.x.x`（不报错）。

> 暂不跑 `pest`：`PermissionMiddleware` 类还不存在；alias 已注册但只有引用到时才加载。安全。

- [ ] **Step 10.4: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add bootstrap/app.php app/Modules/Authorization/Routes/api.php
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): 注册 permission / platform_permission 中间件别名 + Routes 骨架"
```

---

## Task 11: PermissionMiddleware（商户域 + 双路径）

**Spec §5.2, §10 INV-C/D**

**Files:**
- Create: `app/Modules/Authorization/Http/Middleware/PermissionMiddleware.php`
- Create: `app/Modules/Authorization/Tests/Feature/PermissionMiddlewareTest.php`
- Modify: `app/Modules/Identity/Routes/api.php` — 加测试用路由（与 probe 同 testing 守卫）

- [ ] **Step 11.1: 在 Identity Routes 加 testing-only probe 路由专门测 permission**

`app/Modules/Identity/Routes/api.php` 中 testing-only group 内追加：

```php
Route::get('__perm-probe', function () {
    return response()->json(['ok' => true]);
})->middleware('permission:roles.manage');
```

完整 group 现在是：

```php
if (app()->environment('testing')) {
    Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::get('__tenant-probe', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'tenant_id' => app(\App\Support\Tenancy\CurrentTenant::class)->id(),
                'is_platform_impersonation' => $request->attributes->get('is_platform_impersonation'),
                'platform_role_code' => $request->attributes->get('platform_role')?->code,
                'effective_permissions' => $request->attributes->get('effective_permissions'),
                'role_bindings_count' => $request->attributes->get('current_role_bindings')?->count(),
            ]);
        });

        Route::get('__perm-probe', function () {
            return response()->json(['ok' => true]);
        })->middleware('permission:roles.manage');

        Route::post('__perm-probe-write', function () {
            return response()->json(['ok' => true]);
        })->middleware('permission:roles.manage');
    });
}
```

- [ ] **Step 11.2: 写测试**

`app/Modules/Authorization/Tests/Feature/PermissionMiddlewareTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

afterEach(fn () => app(CurrentTenant::class)->set(null));

function prep_member(array $perms, string $scope = 'tenant'): array
{
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => $perms, 'scope' => $scope]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r->id, 'tenant_id' => $t->id]);
    return ['user' => $u, 'tenant' => $t];
}

function prep_platform_admin(array $perms): array
{
    $u = User::factory()->platformAdmin()->create();
    $pr = PlatformRole::factory()->create(['permissions' => $perms]);
    $u->update(['platform_role_id' => $pr->id]);
    $t = Tenant::factory()->create();
    return ['user' => $u, 'tenant' => $t];
}

test('商户员工有目标权限 → 200', function () {
    ['user' => $u, 'tenant' => $t] = prep_member(['roles.manage']);
    Sanctum::actingAs($u);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__perm-probe')->assertOk();
});

test('商户员工缺目标权限 → 403 permission.missing', function () {
    ['user' => $u, 'tenant' => $t] = prep_member(['roles.read']);
    Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__perm-probe');
    $resp->assertStatus(403);
    expect($resp->json('code'))->toBe('permission.missing');
});

test('多 binding 取并集后含目标权限 → 200', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $r1 = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['roles.read']]);
    $r2 = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['roles.manage']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r1->id, 'tenant_id' => $t->id]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r2->id, 'tenant_id' => $t->id]);

    Sanctum::actingAs($u);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__perm-probe')->assertOk();
});

test('revoked binding 不计入', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['roles.manage']]);
    UserRoleBinding::factory()->revoked()->create(['user_id' => $u->id, 'role_id' => $r->id, 'tenant_id' => $t->id]);

    Sanctum::actingAs($u);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__perm-probe')->assertStatus(403);
});

test('platform admin + ImpersonateFull → 任意方法 200', function () {
    ['user' => $u, 'tenant' => $t] = prep_platform_admin(['platform.impersonate.full']);
    Sanctum::actingAs($u);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__perm-probe')->assertOk();
    $this->withHeaders(['X-Tenant-Id' => $t->id])->postJson('/api/__perm-probe-write')->assertOk();
});

test('platform admin + ImpersonateReadOnly + GET → 200', function () {
    ['user' => $u, 'tenant' => $t] = prep_platform_admin(['platform.impersonate.read-only']);
    Sanctum::actingAs($u);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__perm-probe')->assertOk();
});

test('platform admin + ImpersonateReadOnly + POST → 403 method-not-allowed', function () {
    ['user' => $u, 'tenant' => $t] = prep_platform_admin(['platform.impersonate.read-only']);
    Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->postJson('/api/__perm-probe-write');
    $resp->assertStatus(403);
    expect($resp->json('code'))->toBe('platform.impersonation.method-not-allowed');
});

test('platform admin 但无 platform_role → 403', function () {
    $u = User::factory()->platformAdmin()->create();
    $t = Tenant::factory()->create();
    Sanctum::actingAs($u);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__perm-probe')->assertStatus(403);
});
```

- [ ] **Step 11.3: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Feature/PermissionMiddlewareTest.php 2>&1 | tail -10
```
Expected: FAIL "Class PermissionMiddleware not found" 等。

- [ ] **Step 11.4: 实现 PermissionMiddleware**

`app/Modules/Authorization/Http/Middleware/PermissionMiddleware.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Middleware;

use App\Modules\Authorization\Enums\PlatformPermission;
use App\Support\Exceptions\BusinessException;
use Closure;
use Illuminate\Http\Request;

/**
 * 商户域权限校验（spec §5.2，INV-C/D）。
 *
 * 双路径：
 *   - is_platform_impersonation=true：仅消费 platform_role.permissions
 *     · ImpersonateFull → 任意方法放行
 *     · ImpersonateReadOnly + GET/HEAD → 放行
 *     · 其它 → 403 platform.impersonation.method-not-allowed
 *   - is_platform_impersonation=false：仅消费 effective_permissions
 *     · 含目标 permissionKey → 放行
 *     · 不含 → 403 permission.missing
 *
 * 必须挂在 'tenant' 之后（依赖 attributes）。
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permissionKey)
    {
        if ($request->attributes->get('is_platform_impersonation') === true) {
            return $this->handlePlatformImpersonation($request, $next);
        }

        $effective = $request->attributes->get('effective_permissions') ?? [];
        if (! in_array($permissionKey, $effective, strict: true)) {
            throw new BusinessException(
                'permission.missing',
                "missing permission: {$permissionKey}",
                403,
                ['permission_key' => $permissionKey]
            );
        }

        return $next($request);
    }

    private function handlePlatformImpersonation(Request $request, Closure $next)
    {
        $platformRole = $request->attributes->get('platform_role');
        $perms = $platformRole?->permissions ?? [];

        if (in_array(PlatformPermission::ImpersonateFull->value, $perms, strict: true)) {
            return $next($request);
        }

        $isReadOnly = in_array(PlatformPermission::ImpersonateReadOnly->value, $perms, strict: true);
        if ($isReadOnly && in_array($request->method(), ['GET', 'HEAD'], strict: true)) {
            return $next($request);
        }

        throw new BusinessException(
            'platform.impersonation.method-not-allowed',
            'platform impersonation does not allow this method',
            403,
            ['method' => $request->method()]
        );
    }
}
```

- [ ] **Step 11.5: 把 BusinessException 渲染管道接到 bootstrap/app.php**

修改 `bootstrap/app.php` 的 `withExceptions` 段：

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (\App\Support\Exceptions\BusinessException $e, $request) {
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            return null;
        }
        return response()->json([
            'code' => $e->errorCode(),
            'message' => $e->getMessage(),
            'details' => $e->details(),
        ], $e->httpStatus());
    });
})
```

- [ ] **Step 11.6: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 11.7: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/ app/Modules/Identity/Routes/api.php bootstrap/app.php
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): PermissionMiddleware 双路径

商户员工：消费 effective_permissions 含目标 key 放行
平台员工伪装：ImpersonateFull/ReadOnly 分级
INV-C/D：两路径完全互斥，由 is_platform_impersonation 一票决定
连带在 bootstrap/app.php 接入 BusinessException 渲染管道"
```

---

## Task 12: PlatformPermissionMiddleware（平台域）

**Spec §5.3**

**Files:**
- Create: `app/Modules/Authorization/Http/Middleware/PlatformPermissionMiddleware.php`
- Create: `app/Modules/Authorization/Tests/Feature/PlatformPermissionMiddlewareTest.php`
- Modify: `app/Modules/Identity/Routes/api.php` — testing 块加 `__platform-perm-probe` 路由

- [ ] **Step 12.1: 加 testing-only probe 路由**

在 `app/Modules/Identity/Routes/api.php` testing 块**外**新增（因为不需要 tenant 中间件）：

```php
if (app()->environment('testing')) {
    Route::prefix('api')->middleware(['auth:sanctum', 'platform_admin'])->group(function () {
        Route::get('__platform-perm-probe', function () {
            return response()->json(['ok' => true]);
        })->middleware('platform_permission:platform.tenants.manage');
    });
}
```

> 重要：这是**第二个** `if (app()->environment('testing'))` 块（不复用第一个，因为中间件链不同）。

- [ ] **Step 12.2: 写测试**

`app/Modules/Authorization/Tests/Feature/PlatformPermissionMiddlewareTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('platform admin + 含目标平台权限 → 200', function () {
    $u = User::factory()->platformAdmin()->create();
    $pr = PlatformRole::factory()->create(['permissions' => ['platform.tenants.manage']]);
    $u->update(['platform_role_id' => $pr->id]);

    Sanctum::actingAs($u);
    $this->getJson('/api/__platform-perm-probe')->assertOk();
});

test('platform admin 但缺目标权限 → 403', function () {
    $u = User::factory()->platformAdmin()->create();
    $pr = PlatformRole::factory()->create(['permissions' => ['platform.users.manage']]);
    $u->update(['platform_role_id' => $pr->id]);

    Sanctum::actingAs($u);
    $resp = $this->getJson('/api/__platform-perm-probe');
    $resp->assertStatus(403);
    expect($resp->json('code'))->toBe('platform.permission.missing');
});

test('platform admin 但 platform_role_id NULL → 403', function () {
    $u = User::factory()->platformAdmin()->create();
    Sanctum::actingAs($u);
    $this->getJson('/api/__platform-perm-probe')->assertStatus(403);
});

test('非 platform admin → 401/403（被 platform_admin 中间件拦）', function () {
    $u = User::factory()->create();
    Sanctum::actingAs($u);
    $resp = $this->getJson('/api/__platform-perm-probe');
    expect($resp->status())->toBeIn([401, 403]);
});
```

- [ ] **Step 12.3: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Feature/PlatformPermissionMiddlewareTest.php 2>&1 | tail -10
```

- [ ] **Step 12.4: 实现 PlatformPermissionMiddleware**

`app/Modules/Authorization/Http/Middleware/PlatformPermissionMiddleware.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Middleware;

use App\Support\Exceptions\BusinessException;
use Closure;
use Illuminate\Http\Request;

/**
 * 平台域权限校验（spec §5.3）。
 *
 * 不依赖 X-Tenant-Id（平台后台不强制选租户）。
 * 直接从 user.platformRole.permissions 检查。
 *
 * 必须挂在 'auth:sanctum, platform_admin' 之后。
 */
class PlatformPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permissionKey)
    {
        $user = $request->user();
        $perms = $user?->platformRole?->permissions ?? [];

        if (! in_array($permissionKey, $perms, strict: true)) {
            throw new BusinessException(
                'platform.permission.missing',
                "missing platform permission: {$permissionKey}",
                403,
                ['permission_key' => $permissionKey]
            );
        }

        return $next($request);
    }
}
```

- [ ] **Step 12.5: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 12.6: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/ app/Modules/Identity/Routes/api.php
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): PlatformPermissionMiddleware

不依赖 X-Tenant-Id，直接读 user.platformRole.permissions
缺权限/无 platform_role → 403 platform.permission.missing"
```

---

## Task 13: GET /api/me/permissions

**Spec §6.1 #8**

**Files:**
- Create: `app/Modules/Authorization/Http/Controllers/MePermissionsController.php`
- Modify: `app/Modules/Authorization/Routes/api.php` — 加路由
- Create: `app/Modules/Authorization/Tests/Feature/MePermissionsEndpointTest.php`

- [ ] **Step 13.1: 写测试**

`app/Modules/Authorization/Tests/Feature/MePermissionsEndpointTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('普通员工返回 effective_permissions 扁平列表', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['roles.read', 'users.read']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r->id, 'tenant_id' => $t->id]);

    Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/me/permissions');
    $resp->assertOk()->assertJson(['is_platform_impersonation' => false]);
    expect($resp->json('effective_permissions'))->toEqualCanonicalizing(['roles.read', 'users.read']);
    expect($resp->json('platform_permissions'))->toBeNull();
});

test('平台员工伪装态返回 platform_permissions，effective_permissions 为 null', function () {
    $u = User::factory()->platformAdmin()->create();
    $pr = PlatformRole::factory()->create(['permissions' => ['platform.tenants.manage']]);
    $u->update(['platform_role_id' => $pr->id]);
    $t = Tenant::factory()->create();

    Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/me/permissions');
    $resp->assertOk()->assertJson([
        'is_platform_impersonation' => true,
        'effective_permissions' => null,
    ]);
    expect($resp->json('platform_permissions'))->toBe(['platform.tenants.manage']);
});

test('跨租户切 X-Tenant-Id 时 effective_permissions 重新解析', function () {
    $u = User::factory()->create();
    $tA = Tenant::factory()->create();
    $tB = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tA->id]);
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $tB->id]);
    $rA = Role::factory()->create(['tenant_id' => $tA->id, 'permissions' => ['roles.read']]);
    $rB = Role::factory()->create(['tenant_id' => $tB->id, 'permissions' => ['users.read']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $rA->id, 'tenant_id' => $tA->id]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $rB->id, 'tenant_id' => $tB->id]);

    Sanctum::actingAs($u);
    expect($this->withHeaders(['X-Tenant-Id' => $tA->id])->getJson('/api/me/permissions')->json('effective_permissions'))
        ->toBe(['roles.read']);
    expect($this->withHeaders(['X-Tenant-Id' => $tB->id])->getJson('/api/me/permissions')->json('effective_permissions'))
        ->toBe(['users.read']);
});
```

- [ ] **Step 13.2: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Feature/MePermissionsEndpointTest.php 2>&1 | tail -5
```

- [ ] **Step 13.3: 写 controller**

`app/Modules/Authorization/Http/Controllers/MePermissionsController.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MePermissionsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $isPlatformImpersonation = (bool) $request->attributes->get('is_platform_impersonation');

        if ($isPlatformImpersonation) {
            $platformRole = $request->attributes->get('platform_role');
            return response()->json([
                'is_platform_impersonation' => true,
                'platform_role_code' => $platformRole?->code,
                'platform_permissions' => $platformRole?->permissions ?? [],
                'effective_permissions' => null,
            ]);
        }

        return response()->json([
            'is_platform_impersonation' => false,
            'platform_role_code' => null,
            'platform_permissions' => null,
            'effective_permissions' => $request->attributes->get('effective_permissions') ?? [],
        ]);
    }
}
```

- [ ] **Step 13.4: 注册路由（不挂 permission 中间件，只要 tenant 即可）**

`app/Modules/Authorization/Routes/api.php` 替换为：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Http\Controllers\MePermissionsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('me/permissions', [MePermissionsController::class, 'show']);
});
```

- [ ] **Step 13.5: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 13.6: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): GET /api/me/permissions

普通员工返回 effective_permissions
平台伪装态返回 platform_permissions
前端按钮显隐 + 调试用"
```

---

## Task 14: GET /api/roles（列出本租户角色 + 全局模板）

**Spec §6.1 #1**

**Files:**
- Create: `app/Modules/Authorization/Http/Controllers/RoleController.php`
- Modify: `app/Modules/Authorization/Routes/api.php`
- Create: `app/Modules/Authorization/Tests/Feature/RoleEndpointsTest.php`

- [ ] **Step 14.1: 写测试**

`app/Modules/Authorization/Tests/Feature/RoleEndpointsTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function actAsMemberWith(array $perms): array
{
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => $perms]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r->id, 'tenant_id' => $t->id]);
    Sanctum::actingAs($u);
    return ['user' => $u, 'tenant' => $t];
}

test('GET /api/roles 列出本租户角色 + 全局模板', function () {
    ['tenant' => $t] = actAsMemberWith(['roles.read']);

    // 全局模板（已通过 seed migration 存在 3 个）+ 本租户 1 个自建
    Role::factory()->create(['tenant_id' => $t->id, 'name' => '收银员', 'code' => 'Cashier']);
    // 别租户角色不可见
    $other = Tenant::factory()->create();
    Role::factory()->create(['tenant_id' => $other->id, 'name' => '别家员工', 'code' => 'Other']);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/roles');
    $resp->assertOk();
    $names = collect($resp->json('roles'))->pluck('name')->all();
    expect($names)->toContain('商户管理员', '门店店长', '门店店员', '收银员');
    expect($names)->not->toContain('别家员工');
});

test('GET /api/roles 缺权限 → 403', function () {
    ['tenant' => $t] = actAsMemberWith([]);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/roles')->assertStatus(403);
});
```

- [ ] **Step 14.2: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Feature/RoleEndpointsTest.php 2>&1 | tail -5
```

- [ ] **Step 14.3: 实现 RoleController.index**

`app/Modules/Authorization/Http/Controllers/RoleController.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Controllers;

use App\Modules\Authorization\Models\Role;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = app(CurrentTenant::class)->require();

        // 本租户角色 + 全局模板（tenant_id IS NULL）
        $roles = Role::query()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'code', 'scope', 'permissions', 'is_system']);

        return response()->json(['roles' => $roles]);
    }
}
```

- [ ] **Step 14.4: 注册路由（追加到 Routes/api.php）**

`app/Modules/Authorization/Routes/api.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Http\Controllers\MePermissionsController;
use App\Modules\Authorization\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('me/permissions', [MePermissionsController::class, 'show']);

    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.read');
});
```

- [ ] **Step 14.5: 跑 GREEN + 全套**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 14.6: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): GET /api/roles 列出本租户 + 全局模板"
```

---

## Task 15: POST /api/roles（创建商户自建角色）

**Spec §6.1 #2, §7 permission.cross-domain**

**Files:**
- Create: `app/Modules/Authorization/Http/Requests/StoreRoleRequest.php`
- Modify: `app/Modules/Authorization/Http/Controllers/RoleController.php` — 加 `store` 方法
- Modify: `app/Modules/Authorization/Routes/api.php`
- Modify: `app/Modules/Authorization/Tests/Feature/RoleEndpointsTest.php` — 追加测试

- [ ] **Step 15.1: 在 RoleEndpointsTest.php 追加测试**

```php
test('POST /api/roles 创建租户专属角色', function () {
    ['tenant' => $t] = actAsMemberWith(['roles.manage']);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->postJson('/api/roles', [
        'name' => '收银员',
        'code' => 'Cashier',
        'scope' => 'store',
        'permissions' => ['stores.read', 'tenant.read'],
    ]);

    $resp->assertStatus(201);
    expect($resp->json('role.tenant_id'))->toBe($t->id);
    expect($resp->json('role.is_system'))->toBeFalse();
});

test('POST /api/roles 含 platform.* → 422 permission.cross-domain', function () {
    ['tenant' => $t] = actAsMemberWith(['roles.manage']);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->postJson('/api/roles', [
        'name' => '搞事',
        'code' => 'Bad',
        'scope' => 'tenant',
        'permissions' => ['platform.tenants.manage'],
    ]);

    $resp->assertStatus(422);
    expect($resp->json('errors.permissions'))->not->toBeEmpty();
});

test('POST /api/roles 缺 roles.manage → 403', function () {
    ['tenant' => $t] = actAsMemberWith(['roles.read']);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->postJson('/api/roles', [
        'name' => 'X', 'code' => 'X', 'scope' => 'tenant', 'permissions' => [],
    ])->assertStatus(403);
});
```

- [ ] **Step 15.2: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Feature/RoleEndpointsTest.php 2>&1 | tail -5
```

- [ ] **Step 15.3: 写 StoreRoleRequest**

`app/Modules/Authorization/Http/Requests/StoreRoleRequest.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Requests;

use App\Modules\Authorization\Rules\ValidPermissionsRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:60'],
            'scope' => ['required', 'in:tenant,store'],
            'permissions' => ['required', 'array', new ValidPermissionsRule],
        ];
    }
}
```

- [ ] **Step 15.4: 加 RoleController::store**

`app/Modules/Authorization/Http/Controllers/RoleController.php` 追加方法：

```php
public function store(\App\Modules\Authorization\Http\Requests\StoreRoleRequest $request): JsonResponse
{
    $tenantId = app(CurrentTenant::class)->require();

    $role = Role::query()->create([
        'tenant_id' => $tenantId,
        'name' => $request->input('name'),
        'code' => $request->input('code'),
        'scope' => $request->input('scope'),
        'permissions' => $request->input('permissions'),
        'is_system' => false,
    ]);

    return response()->json(['role' => $role], 201);
}
```

- [ ] **Step 15.5: 注册路由**

`app/Modules/Authorization/Routes/api.php` 追加：

```php
Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage');
```

- [ ] **Step 15.6: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 15.7: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): POST /api/roles 创建商户自建角色

强制 tenant_id = X-Tenant-Id，is_system=false
permissions 经 ValidPermissionsRule 校验，拒 platform.*"
```

---

## Task 16: PATCH /api/roles/{id}（更新；is_system 拒改）

**Spec §6.1 #3, §7 role.system-locked**

**Files:**
- Create: `app/Modules/Authorization/Http/Requests/UpdateRoleRequest.php`
- Modify: `app/Modules/Authorization/Http/Controllers/RoleController.php`
- Modify: `app/Modules/Authorization/Routes/api.php`
- Modify: `app/Modules/Authorization/Tests/Feature/RoleEndpointsTest.php`

- [ ] **Step 16.1: 追加测试**

```php
test('PATCH /api/roles/{id} 更新名字 + 权限', function () {
    ['tenant' => $t] = actAsMemberWith(['roles.manage']);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'name' => '原名']);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->patchJson("/api/roles/{$r->id}", ['name' => '新名', 'permissions' => ['roles.read']]);

    $resp->assertOk();
    expect($r->fresh()->name)->toBe('新名');
    expect($r->fresh()->permissions)->toBe(['roles.read']);
});

test('PATCH /api/roles/{id} is_system=true → 403 role.system-locked', function () {
    ['tenant' => $t] = actAsMemberWith(['roles.manage']);
    $r = Role::query()->where('code', 'TenantAdmin')->whereNull('tenant_id')->firstOrFail();

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->patchJson("/api/roles/{$r->id}", ['name' => '篡改']);

    $resp->assertStatus(403);
    expect($resp->json('code'))->toBe('role.system-locked');
});

test('PATCH /api/roles/{id} 改别租户角色 → 404', function () {
    ['tenant' => $t] = actAsMemberWith(['roles.manage']);
    $other = Tenant::factory()->create();
    $r = Role::factory()->create(['tenant_id' => $other->id]);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->patchJson("/api/roles/{$r->id}", ['name' => '篡改']);
    $resp->assertStatus(404);
});
```

- [ ] **Step 16.2: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Feature/RoleEndpointsTest.php 2>&1 | tail -5
```

- [ ] **Step 16.3: 写 UpdateRoleRequest**

`app/Modules/Authorization/Http/Requests/UpdateRoleRequest.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Requests;

use App\Modules\Authorization\Rules\ValidPermissionsRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'permissions' => ['sometimes', 'array', new ValidPermissionsRule],
        ];
    }
}
```

- [ ] **Step 16.4: 加 RoleController::update**

```php
public function update(
    \App\Modules\Authorization\Http\Requests\UpdateRoleRequest $request,
    string $roleId
): JsonResponse {
    $tenantId = app(CurrentTenant::class)->require();

    $role = Role::query()->where('id', $roleId)->where('tenant_id', $tenantId)->first();
    if (! $role) {
        abort(404);
    }

    if ($role->is_system) {
        throw new \App\Support\Exceptions\BusinessException(
            'role.system-locked',
            '系统内置角色不可修改',
            403,
        );
    }

    $role->fill($request->only(['name', 'permissions']))->save();

    return response()->json(['role' => $role]);
}
```

- [ ] **Step 16.5: 注册路由**

```php
Route::patch('roles/{roleId}', [RoleController::class, 'update'])->middleware('permission:roles.manage');
```

- [ ] **Step 16.6: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 16.7: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): PATCH /api/roles/{id} 更新（is_system 锁定）

INV-H：is_system=true 角色不可改/删
跨租户访问 → 404（隐藏存在性）"
```

---

## Task 17: DELETE /api/roles/{id}（被引用 409）

**Spec §6.1 #4, §7 role.in-use**

**Files:**
- Modify: `app/Modules/Authorization/Http/Controllers/RoleController.php`
- Modify: `app/Modules/Authorization/Routes/api.php`
- Modify: `app/Modules/Authorization/Tests/Feature/RoleEndpointsTest.php`

- [ ] **Step 17.1: 追加测试**

```php
test('DELETE /api/roles/{id} 删未被引用的自建角色', function () {
    ['tenant' => $t] = actAsMemberWith(['roles.manage']);
    $r = Role::factory()->create(['tenant_id' => $t->id]);

    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->deleteJson("/api/roles/{$r->id}")->assertStatus(204);

    expect(Role::find($r->id))->toBeNull();
});

test('DELETE /api/roles/{id} 被 active binding 引用 → 409 role.in-use', function () {
    ['user' => $actor, 'tenant' => $t] = actAsMemberWith(['roles.manage']);
    $r = Role::factory()->create(['tenant_id' => $t->id]);
    $other = User::factory()->create();
    Membership::factory()->create(['user_id' => $other->id, 'tenant_id' => $t->id]);
    UserRoleBinding::factory()->create(['user_id' => $other->id, 'role_id' => $r->id, 'tenant_id' => $t->id]);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->deleteJson("/api/roles/{$r->id}");
    $resp->assertStatus(409);
    expect($resp->json('code'))->toBe('role.in-use');
    expect($resp->json('details.binding_count'))->toBe(1);
});

test('DELETE /api/roles/{id} 被 revoked binding 引用不挡删', function () {
    ['tenant' => $t] = actAsMemberWith(['roles.manage']);
    $r = Role::factory()->create(['tenant_id' => $t->id]);
    $other = User::factory()->create();
    UserRoleBinding::factory()->revoked()->create(['user_id' => $other->id, 'role_id' => $r->id, 'tenant_id' => $t->id]);

    $this->withHeaders(['X-Tenant-Id' => $t->id])->deleteJson("/api/roles/{$r->id}")->assertStatus(204);
});

test('DELETE /api/roles/{id} is_system=true → 403 role.system-locked', function () {
    ['tenant' => $t] = actAsMemberWith(['roles.manage']);
    $r = Role::query()->where('code', 'StoreClerk')->whereNull('tenant_id')->firstOrFail();
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->deleteJson("/api/roles/{$r->id}");
    $resp->assertStatus(403);
});
```

- [ ] **Step 17.2: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Feature/RoleEndpointsTest.php 2>&1 | tail -5
```

- [ ] **Step 17.3: 加 RoleController::destroy**

```php
public function destroy(string $roleId): JsonResponse
{
    $tenantId = app(CurrentTenant::class)->require();

    $role = Role::query()->where('id', $roleId)->where('tenant_id', $tenantId)->first();
    if (! $role) {
        abort(404);
    }

    if ($role->is_system) {
        throw new \App\Support\Exceptions\BusinessException(
            'role.system-locked', '系统内置角色不可删除', 403,
        );
    }

    $bindingCount = \App\Modules\Authorization\Models\UserRoleBinding::query()
        ->withoutGlobalScopes()
        ->where('role_id', $roleId)
        ->where('status', 'active')
        ->count();

    if ($bindingCount > 0) {
        throw new \App\Support\Exceptions\BusinessException(
            'role.in-use',
            '角色被在用绑定引用，无法删除',
            409,
            ['binding_count' => $bindingCount],
        );
    }

    $role->delete();
    return response()->json(null, 204);
}
```

- [ ] **Step 17.4: 注册路由**

```php
Route::delete('roles/{roleId}', [RoleController::class, 'destroy'])->middleware('permission:roles.manage');
```

- [ ] **Step 17.5: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 17.6: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): DELETE /api/roles/{id} 含 role.in-use / system-locked 拦截

active binding 引用 → 409 details.binding_count
revoked binding 不算占用
is_system → 403 role.system-locked"
```

---

## Task 18: GET /api/users（列出本租户员工）

**Spec §6.1 #5**

**Files:**
- Create: `app/Modules/Authorization/Http/Controllers/UserListController.php`
- Modify: `app/Modules/Authorization/Routes/api.php`
- Create: `app/Modules/Authorization/Tests/Feature/UserListEndpointTest.php`

- [ ] **Step 18.1: 写测试**

`app/Modules/Authorization/Tests/Feature/UserListEndpointTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function asUserWithUsersRead(): array
{
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['users.read']]);
    UserRoleBinding::factory()->create(['user_id' => $u->id, 'role_id' => $r->id, 'tenant_id' => $t->id]);
    Sanctum::actingAs($u);
    return ['user' => $u, 'tenant' => $t];
}

test('GET /api/users 列出本租户 active 员工', function () {
    ['user' => $self, 'tenant' => $t] = asUserWithUsersRead();
    $u2 = User::factory()->create(['name' => '同事 A']);
    Membership::factory()->create(['user_id' => $u2->id, 'tenant_id' => $t->id, 'status' => 'active']);
    $u3 = User::factory()->create(['name' => '已离职']);
    Membership::factory()->create(['user_id' => $u3->id, 'tenant_id' => $t->id, 'status' => 'left']);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/users');
    $resp->assertOk();
    $names = collect($resp->json('users'))->pluck('name')->all();
    expect($names)->toContain('同事 A');
    expect($names)->not->toContain('已离职');
});

test('GET /api/users 不含别租户员工', function () {
    ['tenant' => $t] = asUserWithUsersRead();
    $other = Tenant::factory()->create();
    $alien = User::factory()->create(['name' => '外人']);
    Membership::factory()->create(['user_id' => $alien->id, 'tenant_id' => $other->id]);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/users');
    $names = collect($resp->json('users'))->pluck('name')->all();
    expect($names)->not->toContain('外人');
});

test('GET /api/users 缺 users.read → 403', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    Sanctum::actingAs($u);
    $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/users')->assertStatus(403);
});
```

- [ ] **Step 18.2: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Feature/UserListEndpointTest.php 2>&1 | tail -5
```

- [ ] **Step 18.3: 实现 UserListController**

`app/Modules/Authorization/Http/Controllers/UserListController.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class UserListController extends Controller
{
    public function index(): JsonResponse
    {
        $tenantId = app(CurrentTenant::class)->require();

        $users = User::query()
            ->whereHas('memberships', fn ($q) => $q->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active'))
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'is_platform_admin', 'last_login_at']);

        return response()->json(['users' => $users]);
    }
}
```

- [ ] **Step 18.4: 注册路由**

```php
Route::get('users', [UserListController::class, 'index'])->middleware('permission:users.read');
```

- [ ] **Step 18.5: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 18.6: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): GET /api/users 列本租户 active 员工

通过 memberships.status=active 过滤
不含 left 成员，不含别租户"
```

---

## Task 19: POST /api/users/{userId}/role-bindings（分配角色）

**Spec §6.1 #6, §7 binding.* 错误码**

**Files:**
- Create: `app/Modules/Authorization/Http/Requests/CreateRoleBindingRequest.php`
- Create: `app/Modules/Authorization/Http/Controllers/RoleBindingController.php`
- Modify: `app/Modules/Authorization/Routes/api.php`
- Create: `app/Modules/Authorization/Tests/Feature/RoleBindingEndpointsTest.php`

- [ ] **Step 19.1: 写测试**

`app/Modules/Authorization/Tests/Feature/RoleBindingEndpointsTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function asUserWithAssignRole(): array
{
    $actor = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $actor->id, 'tenant_id' => $t->id]);
    $role = Role::factory()->create(['tenant_id' => $t->id, 'permissions' => ['users.assign-role']]);
    UserRoleBinding::factory()->create(['user_id' => $actor->id, 'role_id' => $role->id, 'tenant_id' => $t->id]);
    Sanctum::actingAs($actor);
    return ['actor' => $actor, 'tenant' => $t];
}

test('POST 创建 tenant-scope 绑定（无 store_id）', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'tenant', 'permissions' => ['roles.read']]);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", ['role_id' => $r->id]);

    $resp->assertStatus(201);
    expect($resp->json('binding.store_id'))->toBeNull();
});

test('POST 创建 store-scope 绑定（必带 store_id）', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $store = Store::factory()->create(['tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'store', 'permissions' => []]);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", [
            'role_id' => $r->id, 'store_id' => $store->id,
        ]);

    $resp->assertStatus(201);
    expect($resp->json('binding.store_id'))->toBe($store->id);
});

test('store-scope 角色但缺 store_id → 422 binding.store-id-required', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'store']);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", ['role_id' => $r->id]);

    $resp->assertStatus(422);
    expect($resp->json('code'))->toBe('binding.store-id-required');
});

test('tenant-scope 角色但传了 store_id → 422 binding.store-id-not-allowed', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $store = Store::factory()->create(['tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'tenant']);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", [
            'role_id' => $r->id, 'store_id' => $store->id,
        ]);

    $resp->assertStatus(422);
    expect($resp->json('code'))->toBe('binding.store-id-not-allowed');
});

test('store_id 不属于本租户 → 422 binding.store-not-in-tenant', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $other = Tenant::factory()->create();
    $alienStore = Store::factory()->create(['tenant_id' => $other->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'store']);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", [
            'role_id' => $r->id, 'store_id' => $alienStore->id,
        ]);

    $resp->assertStatus(422);
    expect($resp->json('code'))->toBe('binding.store-not-in-tenant');
});

test('全局模板（tenant_id NULL）可以绑定到本租户员工', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $tplt = Role::query()->where('code', 'TenantAdmin')->whereNull('tenant_id')->firstOrFail();

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", ['role_id' => $tplt->id]);

    $resp->assertStatus(201);
});

test('重复绑定相同组合 → 200 幂等返回已存在', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'tenant']);
    $existing = UserRoleBinding::factory()->create([
        'user_id' => $target->id, 'role_id' => $r->id, 'tenant_id' => $t->id,
    ]);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", ['role_id' => $r->id]);

    $resp->assertStatus(200);
    expect($resp->json('binding.id'))->toBe($existing->id);
});

test('revoked binding 重新绑定 → 复活为 active', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'tenant']);
    $revoked = UserRoleBinding::factory()->revoked()->create([
        'user_id' => $target->id, 'role_id' => $r->id, 'tenant_id' => $t->id,
    ]);

    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->postJson("/api/users/{$target->id}/role-bindings", ['role_id' => $r->id]);

    $resp->assertStatus(200);
    expect($revoked->fresh()->status)->toBe('active');
});
```

- [ ] **Step 19.2: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Feature/RoleBindingEndpointsTest.php 2>&1 | tail -10
```

- [ ] **Step 19.3: 写 CreateRoleBindingRequest**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoleBindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_id' => ['required', 'string', 'size:26'],
            'store_id' => ['nullable', 'string', 'size:26'],
        ];
    }
}
```

- [ ] **Step 19.4: 写 RoleBindingController::store**

`app/Modules/Authorization/Http/Controllers/RoleBindingController.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Controllers;

use App\Modules\Authorization\Http\Requests\CreateRoleBindingRequest;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Tenancy\Models\Store;
use App\Support\Exceptions\BusinessException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class RoleBindingController extends Controller
{
    public function store(CreateRoleBindingRequest $request, string $userId): JsonResponse
    {
        $tenantId = app(CurrentTenant::class)->require();
        $roleId = $request->input('role_id');
        $storeId = $request->input('store_id');

        // 1. role 必须属于本租户或全局模板
        $role = Role::query()
            ->where('id', $roleId)
            ->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
            ->first();
        if (! $role) {
            abort(404);
        }

        // 2. scope ↔ store_id 校验
        if ($role->scope === 'store' && ! $storeId) {
            throw new BusinessException('binding.store-id-required', 'store-scope role 必须传 store_id', 422);
        }
        if ($role->scope === 'tenant' && $storeId) {
            throw new BusinessException('binding.store-id-not-allowed', 'tenant-scope role 不可传 store_id', 422);
        }

        // 3. store 必须属于本租户
        if ($storeId) {
            $store = Store::query()->withoutGlobalScopes()
                ->where('id', $storeId)->where('tenant_id', $tenantId)->first();
            if (! $store) {
                throw new BusinessException('binding.store-not-in-tenant', 'store_id 不属于当前租户', 422);
            }
        }

        // 4. 重复 / revoked 检查（应用层去重）
        return DB::transaction(function () use ($userId, $roleId, $tenantId, $storeId, $request) {
            $existingQuery = UserRoleBinding::query()->withoutGlobalScopes()
                ->where('user_id', $userId)
                ->where('role_id', $roleId)
                ->where('tenant_id', $tenantId);
            $existingQuery = $storeId
                ? $existingQuery->where('store_id', $storeId)
                : $existingQuery->whereNull('store_id');

            $existing = $existingQuery->first();

            if ($existing && $existing->status === 'active') {
                return response()->json(['binding' => $existing], 200);
            }

            if ($existing && $existing->status === 'revoked') {
                $existing->update([
                    'status' => 'active',
                    'granted_by' => $request->user()->id,
                    'granted_at' => now(),
                ]);
                return response()->json(['binding' => $existing], 200);
            }

            $binding = UserRoleBinding::query()->withoutGlobalScopes()->create([
                'user_id' => $userId,
                'role_id' => $roleId,
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'status' => 'active',
                'granted_by' => $request->user()->id,
                'granted_at' => now(),
            ]);

            return response()->json(['binding' => $binding], 201);
        });
    }
}
```

- [ ] **Step 19.5: 注册路由**

```php
Route::post('users/{userId}/role-bindings', [RoleBindingController::class, 'store'])
    ->middleware('permission:users.assign-role');
```

- [ ] **Step 19.6: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 19.7: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): POST /api/users/{userId}/role-bindings 分配角色

INV-F：scope ↔ store_id 一致性校验
跨租户 store_id 拒绝
重复幂等 / revoked 复活"
```

---

## Task 20: DELETE /api/role-bindings/{id}（撤销）

**Spec §6.1 #7**

**Files:**
- Modify: `app/Modules/Authorization/Http/Controllers/RoleBindingController.php`
- Modify: `app/Modules/Authorization/Routes/api.php`
- Modify: `app/Modules/Authorization/Tests/Feature/RoleBindingEndpointsTest.php`

- [ ] **Step 20.1: 追加测试**

```php
test('DELETE /api/role-bindings/{id} 撤销改 status=revoked，留痕', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $target = User::factory()->create();
    Membership::factory()->create(['user_id' => $target->id, 'tenant_id' => $t->id]);
    $r = Role::factory()->create(['tenant_id' => $t->id, 'scope' => 'tenant']);
    $b = UserRoleBinding::factory()->create([
        'user_id' => $target->id, 'role_id' => $r->id, 'tenant_id' => $t->id,
    ]);

    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->deleteJson("/api/role-bindings/{$b->id}")->assertStatus(204);

    expect($b->fresh()->status)->toBe('revoked');  // 不删行
});

test('DELETE 别租户的 binding → 404', function () {
    ['tenant' => $t] = asUserWithAssignRole();
    $other = Tenant::factory()->create();
    $b = UserRoleBinding::factory()->create(['tenant_id' => $other->id]);
    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->deleteJson("/api/role-bindings/{$b->id}")->assertStatus(404);
});
```

- [ ] **Step 20.2: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Feature/RoleBindingEndpointsTest.php 2>&1 | tail -5
```

- [ ] **Step 20.3: 加 RoleBindingController::destroy**

```php
public function destroy(string $bindingId): JsonResponse
{
    $tenantId = app(CurrentTenant::class)->require();

    $binding = UserRoleBinding::query()->where('id', $bindingId)->first();
    if (! $binding || $binding->tenant_id !== $tenantId) {
        abort(404);
    }

    $binding->update(['status' => 'revoked']);
    return response()->json(null, 204);
}
```

- [ ] **Step 20.4: 注册路由**

```php
Route::delete('role-bindings/{bindingId}', [RoleBindingController::class, 'destroy'])
    ->middleware('permission:users.assign-role');
```

- [ ] **Step 20.5: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 20.6: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): DELETE /api/role-bindings/{id} 撤销（status=revoked 留痕）"
```

---

## Task 21: 平台角色 CRUD（4 端点）

**Spec §6.2 #9~#12**

**Files:**
- Create: `app/Modules/Authorization/Http/Controllers/Platform/PlatformRoleController.php`
- Create: `app/Modules/Authorization/Http/Requests/StorePlatformRoleRequest.php`
- Create: `app/Modules/Authorization/Http/Requests/UpdatePlatformRoleRequest.php`
- Modify: `app/Modules/Authorization/Routes/api.php`
- Create: `app/Modules/Authorization/Tests/Feature/PlatformRoleEndpointsTest.php`

- [ ] **Step 21.1: 写测试（4 端点合一文件）**

`app/Modules/Authorization/Tests/Feature/PlatformRoleEndpointsTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function asPlatformAdminWith(string $perm): User
{
    $u = User::factory()->platformAdmin()->create();
    $pr = PlatformRole::factory()->create(['permissions' => [$perm]]);
    $u->update(['platform_role_id' => $pr->id]);
    Sanctum::actingAs($u);
    return $u;
}

test('GET /api/platform/roles 列出所有平台角色', function () {
    asPlatformAdminWith('platform.roles.manage');
    $resp = $this->getJson('/api/platform/roles');
    $resp->assertOk();
    expect(count($resp->json('roles')))->toBeGreaterThanOrEqual(3);  // 3 个 is_system 已 seed
});

test('POST /api/platform/roles 创建平台角色', function () {
    asPlatformAdminWith('platform.roles.manage');
    $resp = $this->postJson('/api/platform/roles', [
        'name' => '客服', 'code' => 'PlatformSupport',
        'permissions' => ['platform.impersonate.read-only'],
    ]);
    $resp->assertStatus(201);
    expect($resp->json('role.is_system'))->toBeFalse();
});

test('POST /api/platform/roles 含商户域权限 → 422', function () {
    asPlatformAdminWith('platform.roles.manage');
    $resp = $this->postJson('/api/platform/roles', [
        'name' => 'X', 'code' => 'X', 'permissions' => ['roles.manage'],
    ]);
    $resp->assertStatus(422);
});

test('PATCH /api/platform/roles/{id} 改自建角色', function () {
    asPlatformAdminWith('platform.roles.manage');
    $r = PlatformRole::factory()->create(['name' => '原名', 'code' => 'X']);

    $this->patchJson("/api/platform/roles/{$r->id}", ['name' => '新名'])->assertOk();
    expect($r->fresh()->name)->toBe('新名');
});

test('PATCH is_system → 403 role.system-locked', function () {
    asPlatformAdminWith('platform.roles.manage');
    $r = PlatformRole::query()->where('code', 'PlatformReadOnly')->firstOrFail();

    $resp = $this->patchJson("/api/platform/roles/{$r->id}", ['name' => '篡改']);
    $resp->assertStatus(403);
    expect($resp->json('code'))->toBe('role.system-locked');
});

test('DELETE 自建角色 → 204', function () {
    asPlatformAdminWith('platform.roles.manage');
    $r = PlatformRole::factory()->create(['code' => 'X']);
    $this->deleteJson("/api/platform/roles/{$r->id}")->assertStatus(204);
});

test('DELETE is_system → 403', function () {
    asPlatformAdminWith('platform.roles.manage');
    $r = PlatformRole::query()->where('is_system', true)->first();
    $this->deleteJson("/api/platform/roles/{$r->id}")->assertStatus(403);
});

test('DELETE 被 user 引用 → 409', function () {
    $admin = asPlatformAdminWith('platform.roles.manage');
    $r = PlatformRole::factory()->create();
    $other = User::factory()->platformAdmin()->create(['platform_role_id' => $r->id]);

    $resp = $this->deleteJson("/api/platform/roles/{$r->id}");
    $resp->assertStatus(409);
    expect($resp->json('code'))->toBe('role.in-use');
});

test('缺 platform.roles.manage → 403', function () {
    asPlatformAdminWith('platform.tenants.manage');
    $this->getJson('/api/platform/roles')->assertStatus(403);
});
```

- [ ] **Step 21.2: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Feature/PlatformRoleEndpointsTest.php 2>&1 | tail -10
```

- [ ] **Step 21.3: 写 Form Requests**

`StorePlatformRoleRequest.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Requests;

use App\Modules\Authorization\Rules\ValidPlatformPermissionsRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePlatformRoleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:60', 'unique:platform_roles,code'],
            'permissions' => ['required', 'array', new ValidPlatformPermissionsRule],
        ];
    }
}
```

`UpdatePlatformRoleRequest.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Requests;

use App\Modules\Authorization\Rules\ValidPlatformPermissionsRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformRoleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'permissions' => ['sometimes', 'array', new ValidPlatformPermissionsRule],
        ];
    }
}
```

- [ ] **Step 21.4: 写 PlatformRoleController**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Controllers\Platform;

use App\Modules\Authorization\Http\Requests\StorePlatformRoleRequest;
use App\Modules\Authorization\Http\Requests\UpdatePlatformRoleRequest;
use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\BusinessException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PlatformRoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = PlatformRole::query()->orderBy('is_system', 'desc')->orderBy('name')->get();
        return response()->json(['roles' => $roles]);
    }

    public function store(StorePlatformRoleRequest $request): JsonResponse
    {
        $role = PlatformRole::query()->create($request->validated() + ['is_system' => false]);
        return response()->json(['role' => $role], 201);
    }

    public function update(UpdatePlatformRoleRequest $request, string $id): JsonResponse
    {
        $role = PlatformRole::query()->findOrFail($id);
        if ($role->is_system) {
            throw new BusinessException('role.system-locked', '系统内置角色不可修改', 403);
        }
        $role->fill($request->validated())->save();
        return response()->json(['role' => $role]);
    }

    public function destroy(string $id): JsonResponse
    {
        $role = PlatformRole::query()->findOrFail($id);
        if ($role->is_system) {
            throw new BusinessException('role.system-locked', '系统内置角色不可删除', 403);
        }
        $userCount = User::query()->where('platform_role_id', $id)->count();
        if ($userCount > 0) {
            throw new BusinessException('role.in-use', '角色被用户引用，无法删除', 409, ['user_count' => $userCount]);
        }
        $role->delete();
        return response()->json(null, 204);
    }
}
```

- [ ] **Step 21.5: 注册路由**

`app/Modules/Authorization/Routes/api.php` 在文件**底部**追加（独立 group，不挂 tenant）：

```php
Route::prefix('api/platform')->middleware(['auth:sanctum', 'platform_admin'])->group(function () {
    Route::get('roles', [\App\Modules\Authorization\Http\Controllers\Platform\PlatformRoleController::class, 'index'])
        ->middleware('platform_permission:platform.roles.manage');
    Route::post('roles', [\App\Modules\Authorization\Http\Controllers\Platform\PlatformRoleController::class, 'store'])
        ->middleware('platform_permission:platform.roles.manage');
    Route::patch('roles/{id}', [\App\Modules\Authorization\Http\Controllers\Platform\PlatformRoleController::class, 'update'])
        ->middleware('platform_permission:platform.roles.manage');
    Route::delete('roles/{id}', [\App\Modules\Authorization\Http\Controllers\Platform\PlatformRoleController::class, 'destroy'])
        ->middleware('platform_permission:platform.roles.manage');
});
```

- [ ] **Step 21.6: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 21.7: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): 平台角色 CRUD（4 端点 /api/platform/roles）

INV-H：is_system 角色 PATCH/DELETE 拒
INV-B：permissions 经 ValidPlatformPermissionsRule 校验
被 user 引用 → 409 role.in-use"
```

---

## Task 22: PATCH /api/platform/users/{userId}/platform-role

**Spec §6.2 #13, §7 platform-role.target-not-admin**

**Files:**
- Create: `app/Modules/Authorization/Http/Controllers/Platform/UserPlatformRoleController.php`
- Modify: `app/Modules/Authorization/Routes/api.php`
- Create: `app/Modules/Authorization/Tests/Feature/UserPlatformRoleEndpointTest.php`

- [ ] **Step 22.1: 写测试**

`UserPlatformRoleEndpointTest.php`：

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('PATCH /api/platform/users/{id}/platform-role 给 platform admin 分配', function () {
    $admin = User::factory()->platformAdmin()->create();
    $callerRole = PlatformRole::factory()->create(['permissions' => ['platform.roles.manage']]);
    $admin->update(['platform_role_id' => $callerRole->id]);
    Sanctum::actingAs($admin);

    $target = User::factory()->platformAdmin()->create();
    $newRole = PlatformRole::factory()->create();

    $this->patchJson("/api/platform/users/{$target->id}/platform-role", [
        'platform_role_id' => $newRole->id,
    ])->assertOk();

    expect($target->fresh()->platform_role_id)->toBe($newRole->id);
});

test('给非 platform admin 设 → 422 platform-role.target-not-admin', function () {
    $admin = User::factory()->platformAdmin()->create();
    $callerRole = PlatformRole::factory()->create(['permissions' => ['platform.roles.manage']]);
    $admin->update(['platform_role_id' => $callerRole->id]);
    Sanctum::actingAs($admin);

    $target = User::factory()->create();  // is_platform_admin=false
    $newRole = PlatformRole::factory()->create();

    $resp = $this->patchJson("/api/platform/users/{$target->id}/platform-role", [
        'platform_role_id' => $newRole->id,
    ]);
    $resp->assertStatus(422);
    expect($resp->json('code'))->toBe('platform-role.target-not-admin');
});

test('解绑（platform_role_id=null）', function () {
    $admin = User::factory()->platformAdmin()->create();
    $callerRole = PlatformRole::factory()->create(['permissions' => ['platform.roles.manage']]);
    $admin->update(['platform_role_id' => $callerRole->id]);
    Sanctum::actingAs($admin);

    $target = User::factory()->platformAdmin()->create();
    $oldRole = PlatformRole::factory()->create();
    $target->update(['platform_role_id' => $oldRole->id]);

    $this->patchJson("/api/platform/users/{$target->id}/platform-role", [
        'platform_role_id' => null,
    ])->assertOk();

    expect($target->fresh()->platform_role_id)->toBeNull();
});
```

- [ ] **Step 22.2: 跑 RED**

```bash
./vendor/bin/pest app/Modules/Authorization/Tests/Feature/UserPlatformRoleEndpointTest.php 2>&1 | tail -5
```

- [ ] **Step 22.3: 写 controller**

`UserPlatformRoleController.php`：

```php
<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Http\Controllers\Platform;

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\BusinessException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserPlatformRoleController extends Controller
{
    public function update(Request $request, string $userId): JsonResponse
    {
        $data = $request->validate([
            'platform_role_id' => ['nullable', 'string', 'size:26'],
        ]);

        $target = User::query()->findOrFail($userId);
        if (! $target->is_platform_admin) {
            throw new BusinessException(
                'platform-role.target-not-admin',
                '目标用户不是平台员工',
                422,
            );
        }

        if ($data['platform_role_id']) {
            // 验存在
            PlatformRole::query()->findOrFail($data['platform_role_id']);
        }

        $target->update(['platform_role_id' => $data['platform_role_id']]);
        return response()->json(['user' => $target->only(['id', 'name', 'platform_role_id'])]);
    }
}
```

- [ ] **Step 22.4: 注册路由**

在 Task 21 创建的 platform group 内追加：

```php
Route::patch('users/{userId}/platform-role', [
    \App\Modules\Authorization\Http\Controllers\Platform\UserPlatformRoleController::class, 'update'
])->middleware('platform_permission:platform.roles.manage');
```

- [ ] **Step 22.5: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 22.6: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): PATCH /api/platform/users/{id}/platform-role

INV-G：仅 is_platform_admin=true 的 user 可持有 platform_role_id
非 admin 目标 → 422 platform-role.target-not-admin"
```

---

## Task 23: 给现有 /api/platform/* 端点加 platform_permission 中间件

**Spec §6.3**

**Files:**
- Modify: `app/Modules/Tenancy/Routes/api.php`
- Modify: `app/Modules/Tenancy/Tests/PlatformEndpointsTest.php`

> **影响**：之前"`is_platform_admin=true` 即全开"的语义会改成"必须有对应平台权限码"。原项目所有现存测试都用 `User::factory()->platformAdmin()->create()` 创建，没分配 `platform_role_id`——会全部 403。**必须同步把测试创建的 admin 都关联到 PlatformSuperAdmin（含全部 6 个权限）**。

- [ ] **Step 23.1: 改 Tenancy/Routes/api.php**

```php
<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\Platform\PlatformStoreController;
use App\Modules\Tenancy\Http\Controllers\Platform\PlatformTenantController;
use App\Modules\Tenancy\Http\Controllers\Platform\PlatformUserController;
use App\Modules\Tenancy\Http\Controllers\StoreController;
use App\Modules\Tenancy\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('tenants/current', [TenantController::class, 'current']);
    Route::get('stores', [StoreController::class, 'index']);
});

Route::prefix('api/platform')->middleware(['auth:sanctum', 'platform_admin'])->group(function () {
    Route::post('tenants', [PlatformTenantController::class, 'store'])
        ->middleware('platform_permission:platform.tenants.manage');
    Route::post('tenants/{tenantId}/stores', [PlatformStoreController::class, 'store'])
        ->middleware('platform_permission:platform.stores.manage');
    Route::post('tenants/{tenantId}/users', [PlatformUserController::class, 'store'])
        ->middleware('platform_permission:platform.users.manage');
});
```

- [ ] **Step 23.2: 跑全套，看哪些挂了**

```bash
./vendor/bin/pest 2>&1 | tail -5
```
Expected: PlatformEndpointsTest 中 4-5 个 admin 创建的测试 → 403。

- [ ] **Step 23.3: 修 PlatformEndpointsTest，给每个测试里的 admin 关联 PlatformSuperAdmin**

修改 `app/Modules/Tenancy/Tests/PlatformEndpointsTest.php`，在 `Sanctum::actingAs($admin)` 之前注入 platform role。

把现有每段：

```php
$admin = User::factory()->platformAdmin()->create();
Sanctum::actingAs($admin);
```

替换为：

```php
$admin = User::factory()->platformAdmin()->create();
$superRole = \App\Modules\Authorization\Models\PlatformRole::query()
    ->where('code', 'PlatformSuperAdmin')->firstOrFail();
$admin->update(['platform_role_id' => $superRole->id]);
Sanctum::actingAs($admin);
```

> 已 seed 的 PlatformSuperAdmin 含 4 个 manage 权限 → 涵盖 tenants/stores/users.manage。

> 在 `'非 platform admin 调 platform endpoint 返回 403'` 测试里**不要**注入 platform role（保持原意：非平台 admin 应被 platform_admin 中间件拦在 401/403）。

- [ ] **Step 23.4: 顺手修 TenantReadEndpointsTest 中类似的 platform admin 测试**

```bash
grep -l "platformAdmin()" /mnt/d/Projects/Huang/coffee/app/Modules/Tenancy/Tests/ /mnt/d/Projects/Huang/coffee/app/Modules/Identity/Tests/
```
Expected: PlatformEndpointsTest, TenantReadEndpointsTest, TenantMiddlewareTest（最后一个的两个新测试已经显式分配 platform_role_id 了，不需要改）。

`TenantReadEndpointsTest.php` 里 `'platform admin 任意 X-Tenant-Id 都能列出该租户 stores'` —— 该测试不调 platform 端点（只调 /api/stores），不挂 platform_permission 中间件，不会受影响，**保持原样**。

- [ ] **Step 23.5: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 23.6: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add app/Modules/Tenancy/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(authz): 给现有 /api/platform/{tenants,stores,users} 加 platform_permission 中间件

PlatformSuperAdmin 默认含全部 4 个 manage 权限，向后兼容
其他 platform 角色（如 ReadOnly）将受细粒度限制
PlatformEndpointsTest 中 platform admin 测试同步分配 PlatformSuperAdmin"
```

---

## Task 24: 集成测试（角色撤回链 + 平台员工降级 + 多角色合并）

**Spec §8.4**

**Files:**
- Create: `tests/Integration/Authorization/RoleRevokeChainTest.php`
- Create: `tests/Integration/Authorization/PlatformRoleDowngradeTest.php`
- Create: `tests/Integration/Authorization/MultiBindingMergeTest.php`

- [ ] **Step 24.1: RoleRevokeChainTest**

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('TenantAdmin 撤回后立即失去权限', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $tplt = Role::query()->where('code', 'TenantAdmin')->whereNull('tenant_id')->firstOrFail();
    $b = UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $tplt->id, 'tenant_id' => $t->id,
    ]);

    Sanctum::actingAs($u);

    // Step 1: 拥有 roles.manage，可创建角色
    $this->withHeaders(['X-Tenant-Id' => $t->id])->postJson('/api/roles', [
        'name' => 'X', 'code' => 'X', 'scope' => 'tenant', 'permissions' => [],
    ])->assertStatus(201);

    // Step 2: 撤回该 binding
    $this->withHeaders(['X-Tenant-Id' => $t->id])
        ->deleteJson("/api/role-bindings/{$b->id}")->assertStatus(204);

    // Step 3: 同 token 再调 → 403 permission.missing
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->postJson('/api/roles', [
        'name' => 'Y', 'code' => 'Y', 'scope' => 'tenant', 'permissions' => [],
    ]);
    $resp->assertStatus(403);
    expect($resp->json('code'))->toBe('permission.missing');
});
```

- [ ] **Step 24.2: PlatformRoleDowngradeTest**

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('PlatformSuperAdmin 改成 PlatformReadOnly 后无法 POST', function () {
    $u = User::factory()->platformAdmin()->create();
    $superRole = PlatformRole::query()->where('code', 'PlatformSuperAdmin')->firstOrFail();
    $readonlyRole = PlatformRole::query()->where('code', 'PlatformReadOnly')->firstOrFail();
    $u->update(['platform_role_id' => $superRole->id]);

    Sanctum::actingAs($u);

    // Super 状态：可创建 tenant
    $this->postJson('/api/platform/tenants', ['name' => '九号'])->assertStatus(201);

    // 降级
    $u->update(['platform_role_id' => $readonlyRole->id]);

    // ReadOnly 状态：不能 POST
    $resp = $this->postJson('/api/platform/tenants', ['name' => '示范']);
    $resp->assertStatus(403);
    expect($resp->json('code'))->toBe('platform.permission.missing');
});
```

- [ ] **Step 24.3: MultiBindingMergeTest**

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('一人多 binding 权限并集生效（StoreManager@S1 + StoreClerk@S2）', function () {
    $u = User::factory()->create();
    $t = Tenant::factory()->create();
    Membership::factory()->create(['user_id' => $u->id, 'tenant_id' => $t->id]);
    $s1 = Store::factory()->create(['tenant_id' => $t->id]);
    $s2 = Store::factory()->create(['tenant_id' => $t->id]);

    $manager = Role::query()->where('code', 'StoreManager')->whereNull('tenant_id')->firstOrFail();
    $clerk = Role::query()->where('code', 'StoreClerk')->whereNull('tenant_id')->firstOrFail();

    UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $manager->id, 'tenant_id' => $t->id, 'store_id' => $s1->id,
    ]);
    UserRoleBinding::factory()->create([
        'user_id' => $u->id, 'role_id' => $clerk->id, 'tenant_id' => $t->id, 'store_id' => $s2->id,
    ]);

    Sanctum::actingAs($u);
    $resp = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/me/permissions');

    // StoreManager: roles.read, users.read, tenant.read, stores.read
    // StoreClerk:                          tenant.read, stores.read
    // 并集: roles.read, users.read, tenant.read, stores.read
    expect($resp->json('effective_permissions'))->toEqualCanonicalizing([
        'roles.read', 'users.read', 'tenant.read', 'stores.read',
    ]);

    // role_bindings_count 反映两条 active
    $resp2 = $this->withHeaders(['X-Tenant-Id' => $t->id])->getJson('/api/__tenant-probe');
    expect($resp2->json('role_bindings_count'))->toBe(2);
});
```

- [ ] **Step 24.4: 跑全套**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 24.5: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add tests/Integration/Authorization/
git -C /mnt/d/Projects/Huang/coffee commit -m "test(authz): 集成测试：撤回链 / 平台降级 / 多角色合并

3 个端到端场景验证 RBAC 链路完整性"
```

---

## Task 25: RbacSkeletonSeeder（dev fixture：给已 seeded 的用户分配预设角色）

**Spec §9（database/seeders/）**

**Files:**
- Create: `database/seeders/RbacSkeletonSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` — 串入 RbacSkeletonSeeder
- Create: `tests/Feature/Seeders/RbacSkeletonSeederTest.php`

- [ ] **Step 25.1: 写测试**

```php
<?php

declare(strict_types=1);

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\User;
use Database\Seeders\IdentitySkeletonSeeder;
use Database\Seeders\RbacSkeletonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('RbacSkeletonSeeder 给 13800000000 平台员工绑 PlatformSuperAdmin', function () {
    $this->seed(IdentitySkeletonSeeder::class);
    $this->seed(RbacSkeletonSeeder::class);

    $admin = User::query()->where('phone', '13800000000')->firstOrFail();
    $super = PlatformRole::query()->where('code', 'PlatformSuperAdmin')->firstOrFail();
    expect($admin->platform_role_id)->toBe($super->id);
});

test('两个商户 admin (13900000001, 13900000011) 绑 TenantAdmin', function () {
    $this->seed(IdentitySkeletonSeeder::class);
    $this->seed(RbacSkeletonSeeder::class);

    foreach (['13900000001', '13900000011'] as $phone) {
        $u = User::query()->where('phone', $phone)->firstOrFail();
        $b = UserRoleBinding::query()->withoutGlobalScopes()
            ->where('user_id', $u->id)->where('status', 'active')
            ->with('role')->first();
        expect($b)->not->toBeNull();
        expect($b->role->code)->toBe('TenantAdmin');
    }
});

test('幂等：再跑一次 RBAC seeder 不增加 binding', function () {
    $this->seed(IdentitySkeletonSeeder::class);
    $this->seed(RbacSkeletonSeeder::class);
    $count1 = UserRoleBinding::query()->withoutGlobalScopes()->count();

    $this->seed(RbacSkeletonSeeder::class);
    expect(UserRoleBinding::query()->withoutGlobalScopes()->count())->toBe($count1);
});
```

- [ ] **Step 25.2: 跑 RED**

```bash
./vendor/bin/pest tests/Feature/Seeders/RbacSkeletonSeederTest.php 2>&1 | tail -5
```

- [ ] **Step 25.3: 写 RbacSkeletonSeeder**

`database/seeders/RbacSkeletonSeeder.php`：

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Authorization\Models\PlatformRole;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\Models\UserRoleBinding;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Store;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * 给 IdentitySkeletonSeeder 创建的用户分配预设角色：
 *   - 平台员工 13800000000 → PlatformSuperAdmin（platform_role_id）
 *   - 各租户 admin（13900000001/11）→ TenantAdmin
 *   - 各门店店长（13900000002/04/12）→ StoreManager（绑特定 store）
 *   - 店员（13900000003）→ StoreClerk
 *
 * 全部 firstOrCreate，幂等。依赖 IdentitySkeletonSeeder 先跑。
 */
class RbacSkeletonSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 平台员工
        $admin = User::query()->where('phone', '13800000000')->first();
        $super = PlatformRole::query()->where('code', 'PlatformSuperAdmin')->first();
        if ($admin && $super && ! $admin->platform_role_id) {
            $admin->update(['platform_role_id' => $super->id]);
        }

        // 2. 商户 admins
        $tenantAdminTplt = Role::query()->where('code', 'TenantAdmin')->whereNull('tenant_id')->first();
        $managerTplt = Role::query()->where('code', 'StoreManager')->whereNull('tenant_id')->first();
        $clerkTplt = Role::query()->where('code', 'StoreClerk')->whereNull('tenant_id')->first();

        $jiuhao = Tenant::query()->where('name', '九号咖啡')->first();
        $shifan = Tenant::query()->where('name', '示范咖啡')->first();

        // 九号咖啡管理员
        if ($u = User::query()->where('phone', '13900000001')->first()) {
            $this->bind($u->id, $tenantAdminTplt->id, $jiuhao->id, null);
        }
        // 九号咖啡 徐汇店店长
        if ($u = User::query()->where('phone', '13900000002')->first()) {
            $store = Store::query()->withoutGlobalScopes()
                ->where('tenant_id', $jiuhao->id)->where('name', '上海徐汇店')->first();
            $this->bind($u->id, $managerTplt->id, $jiuhao->id, $store?->id);
        }
        // 九号咖啡 徐汇店店员
        if ($u = User::query()->where('phone', '13900000003')->first()) {
            $store = Store::query()->withoutGlobalScopes()
                ->where('tenant_id', $jiuhao->id)->where('name', '上海徐汇店')->first();
            $this->bind($u->id, $clerkTplt->id, $jiuhao->id, $store?->id);
        }
        // 九号咖啡 朝阳店店长
        if ($u = User::query()->where('phone', '13900000004')->first()) {
            $store = Store::query()->withoutGlobalScopes()
                ->where('tenant_id', $jiuhao->id)->where('name', '北京朝阳店')->first();
            $this->bind($u->id, $managerTplt->id, $jiuhao->id, $store?->id);
        }
        // 示范咖啡 admin
        if ($u = User::query()->where('phone', '13900000011')->first()) {
            $this->bind($u->id, $tenantAdminTplt->id, $shifan->id, null);
        }
        // 示范咖啡 演示门店店员
        if ($u = User::query()->where('phone', '13900000012')->first()) {
            $store = Store::query()->withoutGlobalScopes()
                ->where('tenant_id', $shifan->id)->where('name', '演示门店')->first();
            $this->bind($u->id, $clerkTplt->id, $shifan->id, $store?->id);
        }

        $this->command?->info('=== RBAC fixture 已就绪 ===');
        $this->command?->info('平台员工 13800000000 → PlatformSuperAdmin');
        $this->command?->info('九号咖啡 admin/店长/店员 已绑预设角色');
        $this->command?->info('示范咖啡 admin/店员 已绑预设角色');
    }

    private function bind(string $userId, string $roleId, string $tenantId, ?string $storeId): void
    {
        $query = UserRoleBinding::query()->withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->where('tenant_id', $tenantId);
        $query = $storeId ? $query->where('store_id', $storeId) : $query->whereNull('store_id');

        if ($query->exists()) {
            return;
        }

        UserRoleBinding::query()->withoutGlobalScopes()->create([
            'user_id' => $userId,
            'role_id' => $roleId,
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'status' => 'active',
            'granted_at' => now(),
        ]);
    }
}
```

- [ ] **Step 25.4: 修改 DatabaseSeeder.php 串入**

```php
public function run(): void
{
    $this->call([
        IdentitySkeletonSeeder::class,
        RbacSkeletonSeeder::class,
    ]);
}
```

- [ ] **Step 25.5: 跑 GREEN**

```bash
./vendor/bin/pest 2>&1 | tail -3
```

- [ ] **Step 25.6: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add database/seeders/ tests/Feature/Seeders/
git -C /mnt/d/Projects/Huang/coffee commit -m "feat(seed): RbacSkeletonSeeder 给已 seeded 用户分配预设角色

- 平台员工 → PlatformSuperAdmin
- 商户 admin → TenantAdmin
- 店长/店员 → StoreManager / StoreClerk（绑特定 store）
- 全部 firstOrCreate 幂等

DatabaseSeeder 串入 → migrate:fresh --seed 后即可端到端登录测试"
```

---

## Task 26: lang/zh-CN/errors.php 国际化错误消息 + README 更新

**Spec §7**

**Files:**
- Create: `lang/zh-CN/errors.php`
- Modify: `README.md` — 加 RBAC 段落

- [ ] **Step 26.1: 创建 lang/zh-CN/errors.php**

`lang/zh-CN/errors.php`：

```php
<?php

declare(strict_types=1);

return [
    // RBAC
    'permission.missing' => '缺少权限：:permission_key',
    'platform.permission.missing' => '缺少平台权限：:permission_key',
    'platform.impersonation.method-not-allowed' => '只读伪装态不允许该方法：:method',
    'permission.cross-domain' => '权限码跨域不允许',
    'role.in-use' => '角色被引用，无法删除（绑定数：:binding_count）',
    'role.system-locked' => '系统内置角色不可修改/删除',
    'binding.store-not-in-tenant' => 'store_id 不属于当前租户',
    'binding.store-id-required' => '门店级角色绑定必须传 store_id',
    'binding.store-id-not-allowed' => '租户级角色绑定不可传 store_id',
    'platform-role.target-not-admin' => '目标用户不是平台员工',
];
```

> 注意：当前 BusinessException 渲染管道（在 Task 11 接入的 `bootstrap/app.php`）直接返回 `$e->getMessage()`，并未走 `__()` 翻译。本文件作为后续接入翻译的占位与文档参考；如需真启用，把 `bootstrap/app.php` 的 render 改为 `__('errors.'.$e->errorCode(), $e->details())`。当前所有控制器抛 BusinessException 时 message 已是中文，**保持现状不强行接 i18n**（YAGNI），未来需要多语言再换。

- [ ] **Step 26.2: 改 README.md 加 RBAC 段落**

在 README.md 现有内容后追加：

```markdown
## RBAC（首期 minimal）

权限模型按 `docs/superpowers/specs/2026-05-08-rbac-design.md` 实现：

- 4 张表：roles / platform_roles / user_role_bindings / users.platform_role_id
- 2 个 enum：Permission（商户域 6 个）/ PlatformPermission（平台域 6 个）
- 双中间件：`permission:roles.manage` / `platform_permission:platform.tenants.manage`
- 6 个内置预设角色（migrate 时 seed）：

| 表 | code | 用途 |
|---|---|---|
| roles (全局模板) | TenantAdmin | 商户管理员（全部 6 商户权限） |
| roles (全局模板) | StoreManager | 店长（读 + roles.read + users.read） |
| roles (全局模板) | StoreClerk | 店员（读类） |
| platform_roles | PlatformSuperAdmin | 平台超管（全部 6 平台权限） |
| platform_roles | PlatformOps | 平台运营（不含 read-only impersonation） |
| platform_roles | PlatformReadOnly | 仅 read-only impersonation |

**INV 关键**

- INV-A/B：商户 / 平台权限码严格不互通（双 enum + 双 ValidPermissionsRule 校验）
- INV-C/D：PermissionMiddleware 双路径互斥，`is_platform_impersonation` 一票决定
- INV-F：scope=tenant ↔ store_id NULL；scope=store ↔ store_id 非 NULL
- INV-H：is_system=true 角色不可改/删

**dev 数据**：`php artisan db:seed` 后已用 `secret123` 登录的几个账号都已绑预设角色（见 README 速查表）。
```

- [ ] **Step 26.3: 跑全套（最后一遍）**

```bash
./vendor/bin/pest 2>&1 | tail -3
./vendor/bin/pint --test 2>&1 | tail -3
```
Expected: 全套 PASS + Pint 无问题。如有 Pint 失败，跑 `./vendor/bin/pint` 自动修后再 commit。

- [ ] **Step 26.4: 提交**

```bash
git -C /mnt/d/Projects/Huang/coffee add lang/ README.md
git -C /mnt/d/Projects/Huang/coffee commit -m "docs(authz): lang/zh-CN/errors.php 占位 + README RBAC 段落"
```

---

## 完成验证清单

执行完所有 Task 后，确认：

- [ ] `./vendor/bin/pest 2>&1 | tail -3` 全套绿
- [ ] `./vendor/bin/pint --test 2>&1 | tail -3` 无问题
- [ ] `git log --oneline 2>&1 | head -30` 至少 26 条新 commit（每个 Task 一条）
- [ ] `php artisan migrate:fresh --seed` 成功（在能连 MySQL 的环境跑）
- [ ] 用 `13800000000 / secret123` 登录后能调 `POST /api/platform/tenants` 200
- [ ] 用 `13900000001 / secret123` 登录后调 `POST /api/roles` 200，调 `POST /api/users/{id}/role-bindings` 200
- [ ] 用 `13900000003 / secret123` 登录后调 `POST /api/roles` 收 403 permission.missing（StoreClerk 无 roles.manage）

---

## 不变量回顾（执行时随手对照）

| INV | 实现位置 | 测试位置 |
|---|---|---|
| A | Permission/PlatformPermission enum | PermissionEnumClosureTest, PlatformPermissionEnumClosureTest |
| B | ValidPermissionsRule + ValidPlatformPermissionsRule | ValidPermissionsRuleTest, ValidPlatformPermissionsRuleTest |
| C | PermissionMiddleware：is_platform_impersonation=true 仅消费 platform_role | PermissionMiddlewareTest |
| D | PermissionMiddleware：is_platform_impersonation=false 仅消费 effective_permissions | PermissionMiddlewareTest |
| E | RoleBindingController.store 强制 tenant_id = X-Tenant-Id | RoleBindingEndpointsTest |
| F | RoleBindingController.store 校验 scope ↔ store_id 一致性 | RoleBindingEndpointsTest |
| G | UserPlatformRoleController.update 校验 target.is_platform_admin | UserPlatformRoleEndpointTest |
| H | RoleController.update/destroy + PlatformRoleController.update/destroy is_system 拦截 | RoleEndpointsTest, PlatformRoleEndpointsTest |
