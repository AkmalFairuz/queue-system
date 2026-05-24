<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $this->validatePayload($request, $tenant);

        $tenant->services()->create($validated);

        return response()->json([
            'message' => 'Layanan berhasil ditambahkan.',
        ]);
    }

    public function update(Request $request, Tenant $tenant, Service $service): JsonResponse
    {
        abort_unless($service->tenant_id === $tenant->id, 404);

        $validated = $this->validatePayload($request, $tenant, $service);
        $service->update($validated);

        return response()->json([
            'message' => 'Layanan berhasil diperbarui.',
        ]);
    }

    public function destroy(Tenant $tenant, Service $service): JsonResponse
    {
        abort_unless($service->tenant_id === $tenant->id, 404);

        $service->delete();

        return response()->json([
            'message' => 'Layanan berhasil dihapus.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, Tenant $tenant, ?Service $service = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ticket_prefix' => [
                'required',
                'string',
                'max:10',
                Rule::unique('services', 'ticket_prefix')
                    ->where('tenant_id', $tenant->id)
                    ->ignore($service?->id),
            ],
            'is_login_required' => ['nullable', 'boolean'],
        ]) + [
            'is_login_required' => $request->boolean('is_login_required'),
        ];
    }
}
