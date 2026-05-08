<?php

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
            // 校验 store_id 真的属于该 tenant
            Store::query()->withoutGlobalScopes()
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
