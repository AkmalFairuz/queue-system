<?php

namespace App\Http\Controllers;

use App\Models\Counter;
use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class TenantController extends Controller
{
    public function create(): View
    {
        return view('tenants.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('tenants', 'code')],
            'tts_language' => ['required', 'string', 'max:20'],
            'tts_template' => ['required', 'string', 'max:255'],
        ]);

        $tenant = Tenant::create([
            ...$validated,
            'owner_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.show', $tenant->id)
            ->with('status', 'Tenant berhasil dibuat.');
    }

    public function destroy(Request $request, Tenant $tenant): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->id === $tenant->owner_id, 403);

        $counterIds = $tenant->counters()->pluck('id');
        $serviceIds = $tenant->services()->pluck('id');
        $disableSqliteForeignKeys = DB::getDriverName() === 'sqlite';

        try {
            DB::beginTransaction();

            if ($disableSqliteForeignKeys) {
                DB::statement('PRAGMA foreign_keys = OFF');
            }

            if ($counterIds->isNotEmpty() && DB::getSchemaBuilder()->hasTable('counter_staff')) {
                DB::table('counter_staff')->whereIn('counter_id', $counterIds)->delete();
            }

            DB::table('tenant_user')->where('tenant_id', $tenant->id)->delete();
            Ticket::query()->where('tenant_id', $tenant->id)->delete();

            if ($serviceIds->isNotEmpty()) {
                ServiceSchedule::query()->whereIn('service_id', $serviceIds)->delete();
            }

            Service::query()->where('tenant_id', $tenant->id)->delete();
            Counter::query()->where('tenant_id', $tenant->id)->delete();
            $tenant->delete();

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        } finally {
            if ($disableSqliteForeignKeys) {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }

        $message = 'Tenant berhasil dihapus.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'redirect_url' => route('home'),
            ]);
        }

        return redirect()
            ->route('home')
            ->with('status', $message);
    }
}
