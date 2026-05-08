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
