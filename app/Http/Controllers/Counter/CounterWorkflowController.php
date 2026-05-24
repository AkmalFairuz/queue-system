<?php

namespace App\Http\Controllers\Counter;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\Service;
use App\Models\Tenant;
use App\Services\CounterWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CounterWorkflowController extends Controller
{
    public function updateContext(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'counter_id' => ['required', 'integer'],
            'service_id' => ['required', 'integer'],
        ]);

        $this->findAccessibleCounter($request, $tenant, $validated['counter_id']);
        $tenant->services()->findOrFail($validated['service_id']);

        $request->session()->put($this->counterKey($tenant), $validated['counter_id']);
        $request->session()->put($this->serviceKey($tenant), $validated['service_id']);

        return response()->json([
            'message' => 'Counter dan layanan aktif berhasil dipilih.',
        ]);
    }

    public function callNext(Request $request, Tenant $tenant, CounterWorkflow $workflow): JsonResponse
    {
        [$counter, $service] = $this->resolveContext($request, $tenant);
        $ticket = $workflow->callNext($tenant, $counter, $service);

        return response()->json([
            'message' => 'Tiket berikutnya berhasil dipanggil.',
            'ticket' => [
                'id' => $ticket->id,
                'queue_number' => $ticket->queueNumber(),
            ],
        ]);
    }

    public function startServing(Request $request, Tenant $tenant, CounterWorkflow $workflow): JsonResponse
    {
        [$counter] = $this->resolveContext($request, $tenant);
        $ticket = $workflow->startServing($tenant, $counter);

        return response()->json([
            'message' => 'Pelayanan dimulai.',
            'ticket' => [
                'id' => $ticket->id,
                'queue_number' => $ticket->queueNumber(),
            ],
        ]);
    }

    public function complete(Request $request, Tenant $tenant, CounterWorkflow $workflow): JsonResponse
    {
        [$counter] = $this->resolveContext($request, $tenant);
        $ticket = $workflow->complete($tenant, $counter);

        return response()->json([
            'message' => 'Tiket berhasil diselesaikan.',
            'ticket' => [
                'id' => $ticket->id,
                'queue_number' => $ticket->queueNumber(),
            ],
        ]);
    }

    public function recall(Request $request, Tenant $tenant, CounterWorkflow $workflow): JsonResponse
    {
        [$counter] = $this->resolveContext($request, $tenant);
        $ticket = $workflow->recall($tenant, $counter);

        return response()->json([
            'message' => 'Panggilan ulang berhasil diputar.',
            'ticket' => [
                'id' => $ticket->id,
                'queue_number' => $ticket->queueNumber(),
            ],
        ]);
    }

    public function skip(Request $request, Tenant $tenant, CounterWorkflow $workflow): JsonResponse
    {
        [$counter] = $this->resolveContext($request, $tenant);
        $ticket = $workflow->skip($tenant, $counter);

        return response()->json([
            'message' => 'Tiket berhasil dilewati.',
            'ticket' => [
                'id' => $ticket->id,
                'queue_number' => $ticket->queueNumber(),
            ],
        ]);
    }

    public function cancel(Request $request, Tenant $tenant, CounterWorkflow $workflow): JsonResponse
    {
        [$counter] = $this->resolveContext($request, $tenant);
        $ticket = $workflow->cancel($tenant, $counter);

        return response()->json([
            'message' => 'Tiket berhasil dibatalkan.',
            'ticket' => [
                'id' => $ticket->id,
                'queue_number' => $ticket->queueNumber(),
            ],
        ]);
    }

    /**
     * @return array{0: Counter, 1: Service}
     */
    private function resolveContext(Request $request, Tenant $tenant): array
    {
        $counterId = $request->session()->get($this->counterKey($tenant));
        $serviceId = $request->session()->get($this->serviceKey($tenant));

        abort_unless($counterId && $serviceId, 422, 'Counter atau layanan belum dipilih.');

        return [
            $this->findAccessibleCounter($request, $tenant, (int) $counterId),
            $tenant->services()->findOrFail($serviceId),
        ];
    }

    private function findAccessibleCounter(Request $request, Tenant $tenant, int $counterId): Counter
    {
        $counter = $tenant->counters()->findOrFail($counterId);

        abort_unless($request->user()->canAccessCounter($tenant, $counter), 403);

        return $counter;
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
