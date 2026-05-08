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
