<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantAdminController extends Controller
{
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'in:admin,staff'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user) {
            if (blank($validated['name']) || blank($validated['password'])) {
                throw ValidationException::withMessages([
                    'email' => 'Nama dan kata sandi wajib diisi saat membuat pengguna baru.',
                ]);
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);
        }

        if ($user->id === $tenant->owner_id) {
            throw ValidationException::withMessages([
                'email' => 'Pemilik tenant sudah memiliki akses penuh.',
            ]);
        }

        $tenant->users()->syncWithoutDetaching([
            $user->id => ['role' => $validated['role']],
        ]);

        $tenant->users()->updateExistingPivot($user->id, [
            'role' => $validated['role'],
        ]);

        return response()->json([
            'message' => 'Akses admin/petugas berhasil ditambahkan.',
        ]);
    }

    public function destroy(Tenant $tenant, User $user): JsonResponse
    {
        abort_if($user->id === $tenant->owner_id, 422, 'Pemilik tenant tidak dapat dihapus.');

        $tenant->users()->detach($user->id);

        return response()->json([
            'message' => 'Akses admin/petugas berhasil dihapus.',
        ]);
    }
}
