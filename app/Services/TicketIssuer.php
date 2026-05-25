<?php

namespace App\Services;

use App\Events\QueueDisplayUpdated;
use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TicketStatus;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketIssuer
{
    public function issueForSchedule(Tenant $tenant, Service $service, ServiceSchedule $schedule, ?User $user = null): Ticket
    {
        if ($service->tenant_id !== $tenant->id || $schedule->service_id !== $service->id) {
            throw ValidationException::withMessages([
                'service_schedule_id' => 'Jadwal layanan tidak cocok dengan tenant.',
            ]);
        }

        if ($service->is_login_required && ! $user) {
            throw ValidationException::withMessages([
                'service_schedule_id' => 'Layanan ini membutuhkan pengguna yang sudah masuk.',
            ]);
        }

        $now = now();
        $queueableSchedule = $this->resolveQueueableSchedules($service, $now)
            ->first(fn (array $option) => $option['schedule']->id === $schedule->id);

        if (! $queueableSchedule) {
            throw ValidationException::withMessages([
                'service_schedule_id' => 'Jadwal layanan tidak tersedia pada saat ini.',
            ]);
        }

        /** @var Carbon $serviceDate */
        $serviceDate = $queueableSchedule['service_date'];

        return $this->createTicket($tenant, $schedule, $serviceDate, $user, 'service_schedule_id');
    }

    /**
     * @return Collection<int, array{schedule: ServiceSchedule, service_date: Carbon, queue_opens_at: Carbon, opens_at: Carbon, closes_at: Carbon, is_pre_queue: bool}>
     */
    public function resolveQueueableSchedules(Service $service, ?Carbon $now = null): Collection
    {
        $now ??= now();

        $schedules = $service->relationLoaded('schedules')
            ? $service->schedules
            : $service->schedules()->orderBy('day')->orderBy('opens_at')->get();

        return $schedules
            ->map(function (ServiceSchedule $schedule) use ($now): ?array {
                $context = $schedule->queueContextAt($now);

                if (! $context) {
                    return null;
                }

                return [
                    'schedule' => $schedule,
                    ...$context,
                ];
            })
            ->filter()
            ->sortBy(fn (array $queueableSchedule) => sprintf(
                '%s-%s',
                $queueableSchedule['service_date']->format('Y-m-d'),
                $queueableSchedule['opens_at']->format('H:i:s'),
            ))
            ->values();
    }

    private function createTicket(Tenant $tenant, ServiceSchedule $schedule, Carbon $serviceDate, ?User $user, string $errorKey): Ticket
    {
        $ticket = DB::transaction(function () use ($tenant, $schedule, $serviceDate, $user, $errorKey) {
            $dailyTicketsQuery = Ticket::query()
                ->where('service_schedule_id', $schedule->id)
                ->whereDate('service_date', $serviceDate->toDateString())
                ->lockForUpdate();

            $issuedCount = (clone $dailyTicketsQuery)->count();

            if ($schedule->max_tickets !== null && $issuedCount >= $schedule->max_tickets) {
                throw ValidationException::withMessages([
                    $errorKey => 'Kuota antrian untuk hari ini sudah habis.',
                ]);
            }

            $nextSequence = ((clone $dailyTicketsQuery)->max('sequence') ?? 0) + 1;

            return Ticket::create([
                'user_id' => $user?->id,
                'tenant_id' => $tenant->id,
                'service_schedule_id' => $schedule->id,
                'status' => TicketStatus::Waiting,
                'sequence' => $nextSequence,
                'service_date' => $serviceDate->toDateString(),
            ]);
        });

        $ticket->load('serviceSchedule.service');

        $this->broadcastUpdate($tenant->id, 'ticket-issued');

        return $ticket;
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
