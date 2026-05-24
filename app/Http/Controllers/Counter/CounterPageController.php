<?php

namespace App\Http\Controllers\Counter;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\DashboardDataService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CounterPageController extends Controller
{
    public function show(Request $request, Tenant $tenant, DashboardDataService $dashboardData): View
    {
        return view('counter.show', [
            'tenant' => $tenant,
            'initialData' => $dashboardData->forCounter(
                $tenant,
                $request->user(),
                $request->session()->get($this->counterKey($tenant)),
                $request->session()->get($this->serviceKey($tenant)),
            ),
        ]);
    }

    public function snapshot(Request $request, Tenant $tenant, DashboardDataService $dashboardData): JsonResponse
    {
        return response()->json($dashboardData->forCounter(
            $tenant,
            $request->user(),
            $request->session()->get($this->counterKey($tenant)),
            $request->session()->get($this->serviceKey($tenant)),
        ));
    }

    private function counterKey(Tenant $tenant): string
    {
        return 'counter_context.'.$tenant->id.'.counter_id';
    }

    private function serviceKey(Tenant $tenant): string
    {
        return 'counter_context.'.$tenant->id.'.service_id';
    }
}
