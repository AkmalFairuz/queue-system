<?php

namespace App\Services;

use App\Events\QueueDisplayUpdated;
use App\Models\Counter;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\TicketStatus;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CounterWorkflow
{
    public function callNext(Tenant $tenant, Counter $counter, Service $service): Ticket
    {
        $this->assertTenantMatches($tenant, $counter, $service);

        $ticket = DB::transaction(function () use ($tenant, $counter, $service) {
            $currentTicket = $this->currentTicketQuery($counter)->lockForUpdate()->first();

            if ($currentTicket) {
                throw ValidationException::withMessages([
                    'counter_id' => 'Selesaikan tiket aktif di counter ini terlebih dahulu.',
                ]);
            }

            $nextTicket = Ticket::query()
                ->with('serviceSchedule.service')
                ->where('tenant_id', $tenant->id)
                ->whereDate('service_date', today())
                ->where('status', TicketStatus::Waiting->value)
                ->whereHas('serviceSchedule', function ($query) use ($service) {
                    $query->where('service_id', $service->id);
                })
                ->orderBy('sequence')
                ->lockForUpdate()
                ->first();

            if (! $nextTicket) {
                throw ValidationException::withMessages([
                    'service_id' => 'Belum ada tiket menunggu untuk layanan ini.',
                ]);
            }

            $nextTicket->update([
                'counter_id' => $counter->id,
                'status' => TicketStatus::Called,
                'called_at' => now(),
            ]);

            return $nextTicket->fresh(['serviceSchedule.service', 'counter']);
        });

        $this->broadcastUpdate($tenant->id, 'ticket-called');

        return $ticket;
    }

    public function startServing(Tenant $tenant, Counter $counter): Ticket
    {
        $ticket = DB::transaction(function () use ($tenant, $counter) {
            $ticket = $this->currentTicketQuery($counter)
                ->where('status', TicketStatus::Called->value)
                ->lockForUpdate()
                ->first();

            if (! $ticket) {
                throw ValidationException::withMessages([
                    'counter_id' => 'Tidak ada tiket berstatus dipanggil untuk diproses.',
                ]);
            }

            $ticket->update([
                'status' => TicketStatus::Serving,
                'serving_started_at' => now(),
            ]);

            return $ticket->fresh(['serviceSchedule.service', 'counter']);
        });

        $this->broadcastUpdate($tenant->id, 'ticket-serving');

        return $ticket;
    }

    public function complete(Tenant $tenant, Counter $counter): Ticket
    {
        return $this->finish($tenant, $counter, TicketStatus::Completed, 'completed_at', 'ticket-completed');
    }

    public function skip(Tenant $tenant, Counter $counter): Ticket
    {
        return $this->finish($tenant, $counter, TicketStatus::Skipped, 'skipped_at', 'ticket-skipped');
    }

    public function cancel(Tenant $tenant, Counter $counter): Ticket
    {
        return $this->finish($tenant, $counter, TicketStatus::Cancelled, 'cancelled_at', 'ticket-cancelled');
    }

    public function recall(Tenant $tenant, Counter $counter): Ticket
    {
        $ticket = $this->currentTicketQuery($counter)->first();

        if (! $ticket) {
            throw ValidationException::withMessages([
                'counter_id' => 'Tidak ada tiket aktif pada counter ini.',
            ]);
        }

        $this->broadcastUpdate($tenant->id, 'ticket-recalled', $counter->id, $ticket->id);

        return $ticket;
    }

    private function finish(
        Tenant $tenant,
        Counter $counter,
        TicketStatus $status,
        string $timestampColumn,
        string $reason,
    ): Ticket {
        $ticket = DB::transaction(function () use ($counter, $status, $timestampColumn) {
            $ticket = $this->currentTicketQuery($counter)->lockForUpdate()->first();

            if (! $ticket) {
                throw ValidationException::withMessages([
                    'counter_id' => 'Tidak ada tiket aktif pada counter ini.',
                ]);
            }

            $ticket->update([
                'status' => $status,
                $timestampColumn => now(),
            ]);

            return $ticket->fresh(['serviceSchedule.service', 'counter']);
        });

        $this->broadcastUpdate($tenant->id, $reason);

        return $ticket;
    }

    private function currentTicketQuery(Counter $counter)
    {
        return Ticket::query()
            ->with(['serviceSchedule.service', 'counter'])
            ->where('counter_id', $counter->id)
            ->whereIn('status', TicketStatus::activeValues())
            ->orderByRaw("case when status = 'serving' then 0 else 1 end")
            ->orderByDesc('called_at');
    }

    private function assertTenantMatches(Tenant $tenant, Counter $counter, Service $service): void
    {
        if ($counter->tenant_id !== $tenant->id || $service->tenant_id !== $tenant->id) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Counter atau layanan tidak cocok dengan tenant.',
            ]);
        }
    }

    private function broadcastUpdate(
        int $tenantId,
        string $reason,
        ?int $counterId = null,
        ?int $ticketId = null,
    ): void {
        try {
            event(new QueueDisplayUpdated($tenantId, $reason, $counterId, $ticketId));
        } catch (BroadcastException $exception) {
            report($exception);
        }
    }
}
