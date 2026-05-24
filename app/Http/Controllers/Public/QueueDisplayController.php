<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\DashboardDataService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class QueueDisplayController extends Controller
{
    public function show(Tenant $tenant, DashboardDataService $dashboardData): View
    {
        return view('public.display.show', [
            'tenant' => $tenant,
            'initialData' => $dashboardData->forDisplay($tenant),
        ]);
    }

    public function snapshot(Tenant $tenant, DashboardDataService $dashboardData): JsonResponse
    {
        return response()->json($dashboardData->forDisplay($tenant));
    }
}
