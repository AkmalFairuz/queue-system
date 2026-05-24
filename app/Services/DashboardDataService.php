<?php

namespace App\Services;

use App\Models\Counter;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TicketStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

class DashboardDataService
{
    /**
     * @return array<string, mixed>
     */
    public function forHome(?User $user): array
    {
        $tenants = Tenant::query()->ordered()->get();

        return [
            'tenants' => $tenants->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'code' => $tenant->code,
            ])->all(),
            'managed_tenants' => $user
                ? $tenants->filter(fn (Tenant $tenant) => $tenant->hasUser($user))
                    ->map(fn (Tenant $tenant) => [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'code' => $tenant->code,
                        'can_manage' => $tenant->isManagedBy($user),
                        'can_work' => $tenant->hasUser($user),
                    ])->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forDisplay(Tenant $tenant): array
    {
        $counters = $tenant->counters()->orderBy('name')->get();
        $services = $tenant->services()->orderBy('name')->get();
        $activeTickets = $this->activeTicketsForTenant($tenant);
        $activeTicketsByCounter = $activeTickets->groupBy('counter_id');
        $lastCalledTicketsByService = $this->lastCalledTicketsForTenant($tenant);
        $serviceStats = $this->ticketStatsByService($tenant);

        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'code' => $tenant->code,
                'tts_language' => $tenant->tts_language,
                'tts_template' => $tenant->tts_template,
            ],
            'counters' => $counters->map(function (Counter $counter) use ($activeTicketsByCounter, $tenant) {
                /** @var Ticket|null $ticket */
                $ticket = $activeTicketsByCounter->get($counter->id)?->first();

                return [
                    'id' => $counter->id,
                    'name' => $counter->name,
                    'is_active' => $counter->is_active,
                    'current_ticket' => $ticket ? $this->ticketPayload($ticket, $tenant) : null,
                ];
            })->all(),
            'services' => $services->map(function (Service $service) use ($lastCalledTicketsByService, $serviceStats, $tenant) {
                /** @var Ticket|null $ticket */
                $ticket = $lastCalledTicketsByService->get($service->id);

                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'ticket_prefix' => $service->ticket_prefix,
                    'stats' => $serviceStats->get($service->id, $this->emptyTicketStats()),
                    'last_called_ticket' => $ticket ? $this->ticketPayload($ticket, $tenant) : null,
                ];
            })->all(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forCounter(Tenant $tenant, User $user, ?int $selectedCounterId, ?int $selectedServiceId): array
    {
        $counters = $user->managesTenant($tenant)
            ? $tenant->counters()->orderBy('name')->get()
            : $tenant->counters()
                ->whereHas('staff', fn ($query) => $query->whereKey($user->id))
                ->orderBy('name')
                ->get();
        $services = $tenant->services()->orderBy('name')->get();
        $serviceStats = $this->ticketStatsByService($tenant);
        $selectedCounter = $counters->firstWhere('id', $selectedCounterId);
        $selectedService = $services->firstWhere('id', $selectedServiceId);
        $currentTicket = $selectedCounter ? $this->activeTicketsForTenant($tenant)->firstWhere('counter_id', $selectedCounter->id) : null;

        $nextTickets = collect();

        if ($selectedService) {
            $nextTickets = Ticket::query()
                ->with('serviceSchedule.service')
                ->where('tenant_id', $tenant->id)
                ->whereDate('service_date', today())
                ->where('status', TicketStatus::Waiting->value)
                ->whereHas('serviceSchedule', fn ($query) => $query->where('service_id', $selectedService->id))
                ->orderBy('sequence')
                ->limit(5)
                ->get();
        }

        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ],
            'stats' => $selectedService
                ? $serviceStats->get($selectedService->id, $this->emptyTicketStats())
                : $this->emptyTicketStats(),
            'counters' => $counters->map(fn (Counter $counter) => [
                'id' => $counter->id,
                'name' => $counter->name,
                'is_active' => $counter->is_active,
            ])->all(),
            'services' => $services->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'ticket_prefix' => $service->ticket_prefix,
            ])->all(),
            'selected_counter_id' => $selectedCounter?->id,
            'selected_service_id' => $selectedService?->id,
            'current_ticket' => $currentTicket ? $this->ticketPayload($currentTicket, $tenant) : null,
            'next_tickets' => $nextTickets->map(fn (Ticket $ticket) => $this->ticketPayload($ticket, $tenant))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forAdmin(Tenant $tenant, User $user, array $pageOptions = []): array
    {
        $tenant->load('owner');

        $servicesPaginator = $this->paginateQuery(
            $tenant->services()->withCount('schedules')->orderBy('name'),
            10,
            (int) ($pageOptions['services_page'] ?? 1),
            'services_page',
        );

        $services = $servicesPaginator->getCollection()
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'ticket_prefix' => $service->ticket_prefix,
                'is_login_required' => $service->is_login_required,
                'schedules_count' => $service->schedules_count,
            ])
            ->values()
            ->all();

        $countersPaginator = $this->paginateQuery(
            $tenant->counters()->with('staff:id,name,email')->orderBy('name'),
            10,
            (int) ($pageOptions['counters_page'] ?? 1),
            'counters_page',
        );

        $recentTicketsPaginator = $this->paginateQuery(
            Ticket::query()
                ->with(['serviceSchedule.service', 'counter'])
                ->where('tenant_id', $tenant->id)
                ->whereDate('service_date', today())
                ->latest(),
            10,
            (int) ($pageOptions['tickets_page'] ?? 1),
            'tickets_page',
        );

        $adminsPaginator = $this->paginateCollection(
            $this->adminUsersForTenant($tenant),
            10,
            (int) ($pageOptions['users_page'] ?? 1),
            'users_page',
        );

        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'code' => $tenant->code,
                'tts_language' => $tenant->tts_language,
                'tts_template' => $tenant->tts_template,
            ],
            'permissions' => [
                'can_delete_tenant' => $tenant->owner_id === $user->id,
            ],
            'stats' => $this->ticketStats($tenant),
            'services' => $services,
            'services_pagination' => $this->paginationPayload($servicesPaginator),
            'counters' => $countersPaginator->getCollection()->map(fn (Counter $counter) => [
                'id' => $counter->id,
                'name' => $counter->name,
                'is_active' => $counter->is_active,
                'staff_ids' => $counter->staff->pluck('id')->values()->all(),
                'staff_names' => $counter->staff->pluck('name')->values()->all(),
            ])->values()->all(),
            'counters_pagination' => $this->paginationPayload($countersPaginator),
            'staff_options' => $tenant->users()
                ->wherePivot('role', 'staff')
                ->orderBy('name')
                ->get()
                ->map(fn (User $staff) => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'email' => $staff->email,
                ])->values()->all(),
            'admins' => $adminsPaginator->getCollection()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_owner' => $user->id === $tenant->owner_id,
                'role' => $user->id === $tenant->owner_id ? 'owner' : $user->pivot->role,
            ])->values()->all(),
            'admins_pagination' => $this->paginationPayload($adminsPaginator),
            'recent_tickets' => $recentTicketsPaginator->getCollection()->map(fn (Ticket $ticket) => $this->ticketPayload($ticket, $tenant))->values()->all(),
            'recent_tickets_pagination' => $this->paginationPayload($recentTicketsPaginator),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forAdminSchedules(Tenant $tenant, Service $service, array $pageOptions = []): array
    {
        $schedulePaginator = $this->paginateQuery(
            $service->schedules()->orderBy('day')->orderBy('opens_at'),
            10,
            (int) ($pageOptions['schedules_page'] ?? 1),
            'schedules_page',
        );

        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'code' => $tenant->code,
            ],
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'ticket_prefix' => $service->ticket_prefix,
                'is_login_required' => $service->is_login_required,
            ],
            'schedules' => $schedulePaginator->getCollection()
                ->map(fn ($schedule) => [
                    'id' => $schedule->id,
                    'day' => $schedule->day,
                    'opens_at' => $schedule->opens_at,
                    'closes_at' => $schedule->closes_at,
                    'pre_queue_minutes' => $schedule->pre_queue_minutes,
                    'max_tickets' => $schedule->max_tickets,
                    'is_available' => $schedule->is_available,
                ])->values()->all(),
            'schedules_pagination' => $this->paginationPayload($schedulePaginator),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function ticketStats(Tenant $tenant): array
    {
        $counts = Ticket::query()
            ->where('tenant_id', $tenant->id)
            ->whereDate('service_date', today())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'waiting' => (int) ($counts['waiting'] ?? 0),
            'called' => (int) ($counts['called'] ?? 0),
            'serving' => (int) ($counts['serving'] ?? 0),
            'completed' => (int) ($counts['completed'] ?? 0),
            'skipped' => (int) ($counts['skipped'] ?? 0),
            'cancelled' => (int) ($counts['cancelled'] ?? 0),
        ];
    }

    /**
     * @return Collection<int, Ticket>
     */
    private function activeTicketsForTenant(Tenant $tenant): Collection
    {
        return Ticket::query()
            ->with(['serviceSchedule.service', 'counter'])
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', TicketStatus::activeValues())
            ->orderByRaw("case when status = 'serving' then 0 else 1 end")
            ->orderByDesc('called_at')
            ->get();
    }

    /**
     * @return Collection<int, Ticket>
     */
    private function lastCalledTicketsForTenant(Tenant $tenant): Collection
    {
        return Ticket::query()
            ->with(['serviceSchedule.service', 'counter'])
            ->where('tenant_id', $tenant->id)
            ->whereDate('service_date', today())
            ->whereNotNull('called_at')
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (Ticket $ticket) => $ticket->serviceSchedule->service_id)
            ->mapWithKeys(fn (Ticket $ticket) => [$ticket->serviceSchedule->service_id => $ticket]);
    }

    /**
     * @return SupportCollection<int, array<string, int>>
     */
    private function ticketStatsByService(Tenant $tenant): SupportCollection
    {
        return Ticket::query()
            ->join('service_schedules', 'service_schedules.id', '=', 'tickets.service_schedule_id')
            ->where('tickets.tenant_id', $tenant->id)
            ->whereDate('tickets.service_date', today())
            ->selectRaw('service_schedules.service_id as service_id, tickets.status as status, count(*) as total')
            ->groupBy('service_schedules.service_id', 'tickets.status')
            ->get()
            ->groupBy('service_id')
            ->map(function (SupportCollection $rows): array {
                return $this->ticketStatusCounts(
                    $rows->mapWithKeys(function ($row): array {
                        $status = $row->status instanceof TicketStatus
                            ? $row->status->value
                            : (string) $row->status;

                        return [$status => (int) $row->total];
                    })
                );
            });
    }

    /**
     * @param  SupportCollection<string, int|string>  $counts
     * @return array<string, int>
     */
    private function ticketStatusCounts(SupportCollection $counts): array
    {
        return [
            'waiting' => (int) ($counts['waiting'] ?? 0),
            'called' => (int) ($counts['called'] ?? 0),
            'serving' => (int) ($counts['serving'] ?? 0),
            'completed' => (int) ($counts['completed'] ?? 0),
            'skipped' => (int) ($counts['skipped'] ?? 0),
            'cancelled' => (int) ($counts['cancelled'] ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyTicketStats(): array
    {
        return $this->ticketStatusCounts(collect());
    }

    /**
     * @return SupportCollection<int, User>
     */
    private function adminUsersForTenant(Tenant $tenant): SupportCollection
    {
        return collect([$tenant->owner->setRelation('pivot', (object) ['role' => 'owner'])])
            ->merge($tenant->users()->orderBy('name')->get())
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    private function paginateQuery($query, int $perPage, int $page, string $pageName): LengthAwarePaginator
    {
        $page = max(1, $page);
        $paginator = $query->paginate($perPage, ['*'], $pageName, $page);

        if ($paginator->lastPage() > 0 && $paginator->currentPage() > $paginator->lastPage()) {
            return $query->paginate($perPage, ['*'], $pageName, $paginator->lastPage());
        }

        return $paginator;
    }

    private function paginateCollection(SupportCollection $items, int $perPage, int $page, string $pageName): LengthAwarePaginator
    {
        $page = max(1, $page);
        $total = $items->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min($page, $lastPage);

        return new LengthAwarePaginator(
            $items->forPage($currentPage, $perPage)->values(),
            $total,
            $perPage,
            $currentPage,
            ['pageName' => $pageName],
        );
    }

    /**
     * @return array<string, int|bool|null>
     */
    private function paginationPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_pages' => $paginator->hasPages(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(Ticket $ticket, Tenant $tenant): array
    {
        $service = $ticket->serviceSchedule->service;

        return [
            'id' => $ticket->id,
            'status' => $ticket->status->value,
            'queue_number' => $ticket->queueNumber(),
            'sequence' => $ticket->sequence,
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'ticket_prefix' => $service->ticket_prefix,
            ],
            'counter' => $ticket->counter ? [
                'id' => $ticket->counter->id,
                'name' => $ticket->counter->name,
            ] : null,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'called_at' => $ticket->called_at?->toIso8601String(),
            'serving_started_at' => $ticket->serving_started_at?->toIso8601String(),
            'completed_at' => $ticket->completed_at?->toIso8601String(),
            'cancelled_at' => $ticket->cancelled_at?->toIso8601String(),
            'skipped_at' => $ticket->skipped_at?->toIso8601String(),
            'tts_text' => $tenant->renderTtsTemplate($ticket->queueNumberForTts(), $ticket->counter?->name),
        ];
    }
}
