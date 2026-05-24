<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantSettingsController extends Controller
{
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('tenants', 'code')->ignore($tenant->id)],
            'tts_language' => ['required', 'string', 'max:20'],
            'tts_template' => ['required', 'string', 'max:255'],
        ]);

        $tenant->update($validated);

        return response()->json([
            'message' => 'Pengaturan tenant berhasil diperbarui.',
        ]);
    }
}
