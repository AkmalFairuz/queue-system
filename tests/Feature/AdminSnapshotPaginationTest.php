<?php

namespace Tests\Feature;

use App\Models\Counter;
use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminSnapshotPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_snapshot_uses_server_side_pagination_for_admin_tables(): void
    {
        $owner = User::factory()->create([
            'name' => 'Owner',
        ]);

        $tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $this->createCounters($tenant, 11);
        $this->attachTenantUsers($tenant, 11);
        $services = $this->createServicesWithSchedules($tenant, 6, 6);

        $firstService = $services->first();
        $firstSchedule = ServiceSchedule::query()->where('service_id', $firstService->id)->orderBy('day')->orderBy('opens_at')->firstOrFail();

        $this->createWaitingTickets($tenant, $firstSchedule, 11);

        $response = $this->actingAs($owner)->getJson(route('admin.snapshot', $tenant->id).'?tickets_page=2&services_page=1&counters_page=2&users_page=2');

        $response->assertOk()
            ->assertJsonPath('recent_tickets_pagination.current_page', 2)
            ->assertJsonPath('recent_tickets_pagination.total', 11)
            ->assertJsonCount(1, 'recent_tickets')
            ->assertJsonPath('services_pagination.current_page', 1)
            ->assertJsonPath('services_pagination.total', 6)
            ->assertJsonCount(6, 'services')
            ->assertJsonPath('services.0.schedules_count', 6)
            ->assertJsonPath('counters_pagination.current_page', 2)
            ->assertJsonPath('counters_pagination.total', 11)
            ->assertJsonCount(1, 'counters')
            ->assertJsonPath('admins_pagination.current_page', 2)
            ->assertJsonPath('admins_pagination.total', 12)
            ->assertJsonCount(2, 'admins');
    }

    public function test_admin_service_schedules_snapshot_uses_server_side_pagination(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
        ]);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->createSchedules($service, 11);

        $response = $this->actingAs($owner)->getJson(route('admin.service-schedules.snapshot', [$tenant->id, $service->id]).'?schedules_page=2');

        $response->assertOk()
            ->assertJsonPath('service.id', $service->id)
            ->assertJsonPath('schedules_pagination.current_page', 2)
            ->assertJsonPath('schedules_pagination.total', 11)
            ->assertJsonCount(1, 'schedules');
    }

    private function createCounters(Tenant $tenant, int $count): void
    {
        foreach (range(1, $count) as $index) {
            Counter::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => sprintf('Counter %02d', $index),
            ]);
        }
    }

    private function attachTenantUsers(Tenant $tenant, int $count): void
    {
        foreach (range(1, $count) as $index) {
            $user = User::factory()->create([
                'name' => sprintf('User %02d', $index),
            ]);

            $tenant->users()->attach($user->id, [
                'role' => $index % 2 === 0 ? 'staff' : 'admin',
            ]);
        }
    }

    private function createServicesWithSchedules(Tenant $tenant, int $serviceCount, int $scheduleCount): Collection
    {
        return collect(range(1, $serviceCount))->map(function (int $index) use ($tenant, $scheduleCount) {
            $service = Service::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => sprintf('Service %02d', $index),
                'ticket_prefix' => sprintf('S%d', $index),
            ]);

            $this->createSchedules($service, $scheduleCount);

            return $service;
        });
    }

    private function createSchedules(Service $service, int $count): void
    {
        foreach (range(1, $count) as $index) {
            ServiceSchedule::factory()->create([
                'service_id' => $service->id,
                'day' => $index % 7,
                'opens_at' => sprintf('%02d:00:00', min(23, 7 + $index)),
                'closes_at' => sprintf('%02d:00:00', min(23, 8 + $index)),
            ]);
        }
    }

    private function createWaitingTickets(Tenant $tenant, ServiceSchedule $schedule, int $count): void
    {
        foreach (range(1, $count) as $index) {
            Ticket::create([
                'user_id' => null,
                'tenant_id' => $tenant->id,
                'service_schedule_id' => $schedule->id,
                'status' => TicketStatus::Waiting,
                'sequence' => $index,
                'service_date' => today()->toDateString(),
            ]);
        }
    }
}
