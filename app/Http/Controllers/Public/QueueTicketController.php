<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\DashboardDataService;
use App\Services\TicketIssuer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class QueueTicketController extends Controller
{
    public function index(Tenant $tenant, TicketIssuer $ticketIssuer): View
    {
        $now = now();

        $services = $tenant->services()
            ->with('schedules')
            ->orderBy('name')
            ->get()
            ->map(function (Service $service) use ($ticketIssuer, $now) {
                $queueableSchedules = $ticketIssuer->resolveQueueableSchedules($service, $now);

                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'is_login_required' => $service->is_login_required,
                    'is_open' => $queueableSchedules->isNotEmpty(),
                    'availability_label' => $queueableSchedules->isNotEmpty() ? 'Tersedia' : 'Tutup',
                    'next_service_date_label' => $queueableSchedules->isNotEmpty()
                        ? $this->serviceDateLabel($queueableSchedules->first()['service_date'], $now)
                        : '-',
                ];
            });

        return view('public.tickets.index', [
            'tenant' => $tenant,
            'services' => $services,
        ]);
    }

    public function show(Tenant $tenant, Service $service, TicketIssuer $ticketIssuer): View
    {
        abort_unless($service->tenant_id === $tenant->id, 404);

        $now = now();
        $queueableSchedules = $ticketIssuer->resolveQueueableSchedules($service->loadMissing('schedules'), $now);
        $issuedCounts = Ticket::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('service_schedule_id', $queueableSchedules->pluck('schedule.id')->all())
            ->get()
            ->groupBy(fn (Ticket $ticket) => $this->quotaKey($ticket->service_schedule_id, $ticket->service_date->toDateString()))
            ->map(fn ($tickets) => $tickets->count());
        $dateOptions = $queueableSchedules
            ->groupBy(fn (array $option) => $option['service_date']->toDateString())
            ->map(function ($options, string $dateKey) use ($now): array {
                $serviceDate = $options->first()['service_date'];

                return [
                    'key' => $dateKey,
                    'label' => $this->serviceDateLabel($serviceDate, $now),
                ];
            })
            ->values();

        $serviceData = [
            'id' => $service->id,
            'name' => $service->name,
            'is_open' => $queueableSchedules->isNotEmpty(),
            'availability_label' => $queueableSchedules->isNotEmpty() ? 'Tersedia' : 'Tutup',
            'date_options' => $dateOptions->all(),
            'queue_options' => $queueableSchedules->map(function (array $option) use ($issuedCounts): array {
                /** @var ServiceSchedule $schedule */
                $schedule = $option['schedule'];
                $serviceDateKey = $option['service_date']->toDateString();
                $issuedCount = (int) ($issuedCounts[$this->quotaKey($schedule->id, $serviceDateKey)] ?? 0);
                $remainingQuota = $schedule->max_tickets !== null
                    ? max($schedule->max_tickets - $issuedCount, 0)
                    : null;

                return [
                    'service_schedule_id' => $schedule->id,
                    'date_key' => $serviceDateKey,
                    'schedule_label' => $this->scheduleTimeLabel($schedule),
                    'remaining_quota' => $remainingQuota,
                ];
            })->values()->all(),
        ];

        return view('public.tickets.show', [
            'tenant' => $tenant,
            'service' => $serviceData,
        ]);
    }

    public function ticket(Tenant $tenant, Ticket $ticket, DashboardDataService $dashboardData): View
    {
        abort_unless($ticket->tenant_id === $tenant->id, 404);

        $ticket->loadMissing(['serviceSchedule.service', 'counter']);

        $displayData = $dashboardData->forDisplay($tenant);
        $service = collect($displayData['services'])
            ->firstWhere('id', $ticket->serviceSchedule->service_id);

        return view('public.tickets.ticket', [
            'tenant' => $tenant,
            'ticket' => [
                'id' => $ticket->id,
                'queue_number' => $ticket->queueNumber(),
                'service_name' => $ticket->serviceSchedule->service->name,
                'service_id' => $ticket->serviceSchedule->service_id,
                'service_date_label' => $this->serviceDateLabel($ticket->service_date, now()),
                'schedule_label' => $this->scheduleTimeLabel($ticket->serviceSchedule),
            ],
            'initialDisplayService' => $service,
        ]);
    }

    public function store(Request $request, Tenant $tenant, TicketIssuer $ticketIssuer): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'service_schedule_id' => ['required', 'integer'],
        ]);

        $service = $tenant->services()->findOrFail($validated['service_id']);
        $schedule = $service->schedules()->findOrFail($validated['service_schedule_id']);
        $ticket = $ticketIssuer->issueForSchedule($tenant, $service, $schedule, $request->user());

        return response()->json([
            'message' => 'Nomor antrian berhasil diambil.',
            'redirect_url' => route('tenant.queue.ticket', [$tenant->code, $ticket->id]),
            'ticket' => [
                'id' => $ticket->id,
                'queue_number' => $ticket->queueNumber(),
                'service_name' => $ticket->serviceSchedule->service->name,
                'service_date_label' => $this->serviceDateLabel($ticket->service_date, now()),
                'schedule_label' => $this->scheduleTimeLabel($ticket->serviceSchedule),
            ],
        ]);
    }

    private function serviceDateLabel(Carbon $serviceDate, Carbon $referenceTime): string
    {
        if ($serviceDate->isSameDay($referenceTime)) {
            return sprintf('Hari ini · %s', $serviceDate->format('d/m/Y'));
        }

        if ($serviceDate->isSameDay($referenceTime->copy()->addDay())) {
            return sprintf('Besok · %s', $serviceDate->format('d/m/Y'));
        }

        return $serviceDate->format('d/m/Y');
    }

    private function scheduleTimeLabel(ServiceSchedule $schedule): string
    {
        return sprintf('%s - %s', $schedule->opens_at ?? '00:00', $schedule->closes_at ?? '23:59');
    }

    private function quotaKey(int $serviceScheduleId, string $serviceDate): string
    {
        return sprintf('%d:%s', $serviceScheduleId, $serviceDate);
    }
}
