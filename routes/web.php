<?php

declare(strict_types=1);

use App\Modules\Authorization\Http\Controllers\Web\TenantRoleController;
use App\Modules\Catalog\Http\Controllers\Web\TenantCategoryController;
use App\Modules\Catalog\Http\Controllers\Web\TenantItemController;
use App\Modules\Identity\Http\Controllers\Web\AuthController;
use App\Modules\Identity\Http\Controllers\Web\StoreUserController;
use App\Modules\Identity\Http\Controllers\Web\TenantProfileController;
use App\Modules\Identity\Http\Controllers\Web\TenantUserController;
use App\Modules\Tenancy\Http\Controllers\Platform\PlatformStoreController;
use App\Modules\Tenancy\Http\Controllers\Platform\PlatformTenantController;
use App\Modules\Inventory\Http\Controllers\Web\TenantBomController;
use App\Modules\Inventory\Http\Controllers\Web\TenantInventoryConfigController;
use App\Modules\Inventory\Http\Controllers\Web\TenantStoreInventoryConfigController;
use App\Modules\Inventory\Http\Controllers\Web\TenantStockController;
use App\Modules\Inventory\Http\Controllers\Web\TenantStockMutationController;
use App\Modules\Tenancy\Http\Controllers\Web\TenantStoreController;
use App\Support\Tenancy\WebTenantAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * 前端视图迁移阶段：所有路由都是 stub，渲染 Inertia 页面 + 空数据，
 * 仅用于让搬过来的 supermarket 视图骨架能跑起来。后续按模块逐步接真实控制器。
 */

$paginated = fn (array $extra = []) => array_merge([
    'rows' => [],
    'total' => 0,
    'page' => 1,
    'pageSize' => 20,
    'q' => '',
    'status' => 'all',
], $extra);

$tenantStub = ['id' => 'TENANT_STUB', 'name' => '示范咖啡', 'status' => 'active'];
$storeStub  = ['id' => 'STORE_STUB',  'name' => '徐汇店',  'status' => 'active'];

// ---------- 公共入口 ----------
Route::get('/', fn () => Inertia::render('Welcome'));
Route::get('/login', fn () => Inertia::render('tenant/Login'));
Route::get('/select-tenant', fn () => Inertia::render('SelectTenant'));

// ---------- 平台后台 ----------
Route::prefix('platform')->group(function () use ($paginated, $tenantStub) {
    // 公开（未登录可访问）
    Route::get('login', fn () => Inertia::render('platform/Login'));
    Route::post('login', [AuthController::class, 'platformLogin']);

    // 已登录平台员工
    Route::middleware(['auth', 'web.platform_admin'])->group(function () use ($paginated, $tenantStub) {
        Route::post('logout', [AuthController::class, 'platformLogout']);
        Route::get('dashboard', fn () => Inertia::render('platform/Dashboard'));

        Route::get('tenants', [PlatformTenantController::class, 'index']);
        Route::get('tenants/create', [PlatformTenantController::class, 'create']);
        Route::post('tenants', [PlatformTenantController::class, 'storeFromForm']);
        Route::get('tenants/{id}/edit', [PlatformTenantController::class, 'edit']);
        Route::patch('tenants/{id}', [PlatformTenantController::class, 'update']);
        Route::post('tenants/{id}/enter', function (string $id, \Illuminate\Http\Request $request) {
            // 进入租户后台：写 session 后跳转 /tenant/dashboard，由 HandleInertiaRequests 注入 tenant.current
            // 切换租户时必须清掉旧 store 选择，避免上一个租户的 store_id 被带过来。
            $tenant = \App\Modules\Tenancy\Models\Tenant::query()->whereKey($id)->firstOrFail();
            $request->session()->put('current_tenant_id', $tenant->id);
            $request->session()->put('current_tenant_name', $tenant->name);
            $request->session()->forget(['current_store_id', 'current_store_name']);
            return redirect('/tenant/dashboard');
        });

        Route::get('tenants/{tenantId}/stores', [PlatformStoreController::class, 'index']);
        Route::get('tenants/{tenantId}/stores/create', [PlatformStoreController::class, 'create']);
        Route::post('tenants/{tenantId}/stores', [PlatformStoreController::class, 'storeFromForm']);
        Route::get('tenants/{tenantId}/stores/{storeId}/edit', [PlatformStoreController::class, 'edit']);
        Route::patch('tenants/{tenantId}/stores/{storeId}', [PlatformStoreController::class, 'update']);
    });
});

// ---------- 租户/门店后台 ----------
Route::prefix('tenant')->group(function () use ($paginated, $storeStub) {
    // 公开（未登录可访问）
    Route::get('login', fn () => Inertia::render('tenant/Login'));
    Route::post('login', [AuthController::class, 'tenantLogin']);
});

Route::prefix('tenant')->middleware('auth')->group(function () use ($paginated, $storeStub) {
    Route::post('logout', [AuthController::class, 'tenantLogout']);
    Route::post('store/switch', function (\Illuminate\Http\Request $request) {
        $tenantId = $request->session()->get('current_tenant_id');
        if (! $tenantId) {
            return back()->withErrors(['store_id' => '尚未选定租户']);
        }
        $available = WebTenantAccess::availableStores(Auth::guard('web')->user(), $tenantId);
        $id = (string) $request->input('store_id');
        $hit = collect($available)->firstWhere('id', $id);
        if (! $hit) {
            return back()->withErrors(['store_id' => '门店不存在或无权访问']);
        }
        $request->session()->put('current_store_id', $hit['id']);
        $request->session()->put('current_store_name', $hit['name']);
        $request->session()->flash('success', "已切换至：{$hit['name']}");
        return back();
    });
    Route::get('dashboard', fn () => Inertia::render('tenant/Dashboard'));
    Route::get('profile', [TenantProfileController::class, 'edit']);
    Route::patch('profile', [TenantProfileController::class, 'update']);
    Route::patch('profile/password', [TenantProfileController::class, 'updatePassword']);

    Route::get('users', [TenantUserController::class, 'index']);
    Route::get('users/create', [TenantUserController::class, 'create']);
    Route::post('users', [TenantUserController::class, 'storeFromForm']);
    Route::get('users/{userId}/edit', [TenantUserController::class, 'edit']);
    Route::patch('users/{userId}', [TenantUserController::class, 'update']);
    Route::delete('users/{userId}', [TenantUserController::class, 'destroy']);
    Route::post('users/{userId}/reset-password', [TenantUserController::class, 'resetPassword']);

    Route::get('roles', [TenantRoleController::class, 'index']);
    Route::get('roles/create', [TenantRoleController::class, 'create']);
    Route::post('roles', [TenantRoleController::class, 'storeFromForm']);
    Route::get('roles/{roleId}/edit', [TenantRoleController::class, 'edit']);
    Route::patch('roles/{roleId}', [TenantRoleController::class, 'update']);
    Route::delete('roles/{roleId}', [TenantRoleController::class, 'destroy']);

    Route::get('categories', [TenantCategoryController::class, 'index']);
    Route::get('categories/create', [TenantCategoryController::class, 'create']);
    Route::post('categories', [TenantCategoryController::class, 'storeFromForm']);
    Route::get('categories/{id}/edit', [TenantCategoryController::class, 'edit']);
    Route::patch('categories/{id}', [TenantCategoryController::class, 'update']);
    Route::delete('categories/{id}', [TenantCategoryController::class, 'destroy']);

    Route::get('items', [TenantItemController::class, 'index']);
    Route::get('items/create', [TenantItemController::class, 'create']);
    Route::post('items', [TenantItemController::class, 'storeFromForm']);
    Route::get('items/{id}/edit', [TenantItemController::class, 'edit']);
    Route::patch('items/{id}', [TenantItemController::class, 'update']);

    Route::get('boms', [TenantBomController::class, 'index']);
    Route::get('boms/create', [TenantBomController::class, 'create']);
    Route::post('boms', [TenantBomController::class, 'storeFromForm']);
    Route::get('boms/{id}/edit', [TenantBomController::class, 'edit']);
    Route::patch('boms/{id}', [TenantBomController::class, 'update']);
    Route::delete('boms/{id}', [TenantBomController::class, 'destroy']);

    Route::get('settings/inventory', [TenantInventoryConfigController::class, 'show']);
    Route::patch('settings/inventory', [TenantInventoryConfigController::class, 'update']);

    Route::get('stock', [TenantStockController::class, 'index']);
    Route::get('stock/txns', [TenantStockController::class, 'txns']);
    Route::get('stock/adjust', [TenantStockMutationController::class, 'adjustForm']);
    Route::post('stock/adjust', [TenantStockMutationController::class, 'adjust']);
    Route::get('stock/stocktake', [TenantStockMutationController::class, 'stocktakeForm']);
    Route::post('stock/stocktake', [TenantStockMutationController::class, 'stocktake']);
    Route::get('stock/damage', [TenantStockMutationController::class, 'damageForm']);
    Route::post('stock/damage', [TenantStockMutationController::class, 'damage']);
    Route::post('stock/txns/{id}/reverse', [TenantStockMutationController::class, 'reverse']);

    Route::get('stores', [TenantStoreController::class, 'index']);
    Route::get('stores/{storeId}/inventory', [TenantStoreInventoryConfigController::class, 'show']);
    Route::patch('stores/{storeId}/inventory', [TenantStoreInventoryConfigController::class, 'update']);
    Route::get('stores/{storeId}/users', [StoreUserController::class, 'index']);
    Route::get('stores/{storeId}/users/create', [StoreUserController::class, 'create']);
    Route::post('stores/{storeId}/users', [StoreUserController::class, 'storeFromForm']);
    Route::get('stores/{storeId}/users/{userId}/edit', [StoreUserController::class, 'edit']);
    Route::patch('stores/{storeId}/users/{userId}', [StoreUserController::class, 'update']);
    Route::delete('stores/{storeId}/users/{userId}', [StoreUserController::class, 'destroy']);
});
