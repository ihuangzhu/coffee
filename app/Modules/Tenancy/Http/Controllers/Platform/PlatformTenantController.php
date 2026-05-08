<?php

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
