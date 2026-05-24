<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Tenant;
use App\Services\DashboardDataService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function show(Request $request, Tenant $tenant, DashboardDataService $dashboardData): View
    {
        return $this->renderPage($request, $tenant, $dashboardData, 'overview', 'Ringkasan');
    }

    public function services(Request $request, Tenant $tenant, DashboardDataService $dashboardData): View
    {
        return $this->renderPage($request, $tenant, $dashboardData, 'services', 'Layanan');
    }

    public function counters(Request $request, Tenant $tenant, DashboardDataService $dashboardData): View
    {
        return $this->renderPage($request, $tenant, $dashboardData, 'counters', 'Counter');
    }

    public function users(Request $request, Tenant $tenant, DashboardDataService $dashboardData): View
    {
        return $this->renderPage($request, $tenant, $dashboardData, 'users', 'Akses');
    }

    public function settings(Request $request, Tenant $tenant, DashboardDataService $dashboardData): View
    {
        return $this->renderPage($request, $tenant, $dashboardData, 'settings', 'Pengaturan');
    }

    public function serviceSchedules(Request $request, Tenant $tenant, Service $service, DashboardDataService $dashboardData): View
    {
        abort_unless($service->tenant_id === $tenant->id, 404);

        return view('admin.page', [
            'tenant' => $tenant,
            'section' => 'service-schedules',
            'navSection' => 'services',
            'pageTitle' => 'Jadwal Layanan',
            'breadcrumbs' => [
                ['label' => 'Beranda', 'href' => route('home')],
                ['label' => $tenant->name, 'href' => route('admin.show', $tenant->id)],
                ['label' => 'Layanan', 'href' => route('admin.services.page', $tenant->id)],
                ['label' => $service->name],
            ],
            'initialData' => $dashboardData->forAdminSchedules($tenant, $service, $this->pageOptions($request)),
        ]);
    }

    public function snapshot(Request $request, Tenant $tenant, DashboardDataService $dashboardData): JsonResponse
    {
        return response()->json($dashboardData->forAdmin($tenant, $request->user(), $this->pageOptions($request)));
    }

    public function serviceSchedulesSnapshot(Request $request, Tenant $tenant, Service $service, DashboardDataService $dashboardData): JsonResponse
    {
        abort_unless($service->tenant_id === $tenant->id, 404);

        return response()->json($dashboardData->forAdminSchedules($tenant, $service, $this->pageOptions($request)));
    }

    private function renderPage(Request $request, Tenant $tenant, DashboardDataService $dashboardData, string $section, string $pageTitle): View
    {
        return view('admin.page', [
            'tenant' => $tenant,
            'section' => $section,
            'pageTitle' => $pageTitle,
            'initialData' => $dashboardData->forAdmin($tenant, $request->user(), $this->pageOptions($request)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageOptions(Request $request): array
    {
        return [
            'tickets_page' => (int) $request->integer('tickets_page', 1),
            'services_page' => (int) $request->integer('services_page', 1),
            'counters_page' => (int) $request->integer('counters_page', 1),
            'users_page' => (int) $request->integer('users_page', 1),
            'schedules_page' => (int) $request->integer('schedules_page', 1),
        ];
    }
}
