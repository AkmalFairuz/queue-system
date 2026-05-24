<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CounterController extends Controller
{
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $this->validatePayload($request, $tenant);
        $counter = $tenant->counters()->create(collect($validated)->except('staff_ids')->all());

        $counter->staff()->sync($validated['staff_ids'] ?? []);

        return response()->json([
            'message' => 'Counter berhasil ditambahkan.',
        ]);
    }

    public function update(Request $request, Tenant $tenant, Counter $counter): JsonResponse
    {
        abort_unless($counter->tenant_id === $tenant->id, 404);

        $validated = $this->validatePayload($request, $tenant, $counter);
        $counter->update(collect($validated)->except('staff_ids')->all());
        $counter->staff()->sync($validated['staff_ids'] ?? []);

        return response()->json([
            'message' => 'Counter berhasil diperbarui.',
        ]);
    }

    public function destroy(Tenant $tenant, Counter $counter): JsonResponse
    {
        abort_unless($counter->tenant_id === $tenant->id, 404);

        $counter->delete();

        return response()->json([
            'message' => 'Counter berhasil dihapus.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, Tenant $tenant, ?Counter $counter = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('counters', 'name')
                    ->where('tenant_id', $tenant->id)
                    ->ignore($counter?->id),
            ],
            'is_active' => ['nullable', 'boolean'],
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => [
                'integer',
                Rule::exists('tenant_user', 'user_id')
                    ->where(fn ($query) => $query
                        ->where('tenant_id', $tenant->id)
                        ->where('role', 'staff')),
            ],
        ]) + [
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
