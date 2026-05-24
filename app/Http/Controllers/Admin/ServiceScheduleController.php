<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceSchedule;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceScheduleController extends Controller
{
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $this->validatePayload($request, $tenant);

        $service = $tenant->services()->findOrFail($validated['service_id']);
        $service->schedules()->create($validated);

        return response()->json([
            'message' => 'Jadwal layanan berhasil ditambahkan.',
        ]);
    }

    public function update(Request $request, Tenant $tenant, ServiceSchedule $serviceSchedule): JsonResponse
    {
        abort_unless($serviceSchedule->service->tenant_id === $tenant->id, 404);

        $validated = $this->validatePayload($request, $tenant, $serviceSchedule);
        $serviceSchedule->update($validated);

        return response()->json([
            'message' => 'Jadwal layanan berhasil diperbarui.',
        ]);
    }

    public function destroy(Tenant $tenant, ServiceSchedule $serviceSchedule): JsonResponse
    {
        abort_unless($serviceSchedule->service->tenant_id === $tenant->id, 404);

        $serviceSchedule->delete();

        return response()->json([
            'message' => 'Jadwal layanan berhasil dihapus.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, Tenant $tenant, ?ServiceSchedule $serviceSchedule = null): array
    {
        return $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('tenant_id', $tenant->id)],
            'day' => ['required', 'integer', 'between:0,6'],
            'opens_at' => ['nullable', 'date_format:H:i'],
            'closes_at' => ['nullable', 'date_format:H:i', 'after:opens_at'],
            'pre_queue_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'max_tickets' => ['nullable', 'integer', 'min:1'],
            'is_available' => ['nullable', 'boolean'],
        ]) + [
            'pre_queue_minutes' => (int) $request->integer('pre_queue_minutes', 0),
            'is_available' => $request->boolean('is_available'),
        ];
    }
}
