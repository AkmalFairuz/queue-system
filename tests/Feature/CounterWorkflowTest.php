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
use Tests\TestCase;

class CounterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_counter_can_call_start_and_complete_ticket(): void
    {
        config(['broadcasting.default' => 'null']);

        $owner = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
        ]);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_prefix' => 'CS',
        ]);
        $schedule = ServiceSchedule::factory()->create([
            'service_id' => $service->id,
            'day' => now()->dayOfWeekIso - 1,
        ]);
        $counter = Counter::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Counter A',
        ]);

        $ticket = $this->createTicket($tenant, $schedule);

        $this->selectCounterContext($owner, $tenant, $counter, $service);

        $this->actingAs($owner)
            ->postJson(route('counter.call-next', $tenant->id))
            ->assertOk()
            ->assertJsonPath('ticket.queue_number', 'CS-1');

        $ticket->refresh();
        $this->assertSame(TicketStatus::Called, $ticket->status);
        $this->assertSame($counter->id, $ticket->counter_id);

        $this->actingAs($owner)
            ->postJson(route('counter.start-serving', $tenant->id))
            ->assertOk();

        $ticket->refresh();
        $this->assertSame(TicketStatus::Serving, $ticket->status);

        $this->actingAs($owner)
            ->postJson(route('counter.complete', $tenant->id))
            ->assertOk();

        $ticket->refresh();
        $this->assertSame(TicketStatus::Completed, $ticket->status);
    }

    public function test_counter_snapshot_stats_only_include_selected_service(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
        ]);
        $counter = Counter::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Counter A',
        ]);

        $serviceA = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_prefix' => 'CS',
        ]);
        $scheduleA = ServiceSchedule::factory()->create([
            'service_id' => $serviceA->id,
            'day' => now()->dayOfWeekIso - 1,
        ]);

        $serviceB = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_prefix' => 'IN',
        ]);
        $scheduleB = ServiceSchedule::factory()->create([
            'service_id' => $serviceB->id,
            'day' => now()->dayOfWeekIso - 1,
        ]);

        $this->createTicket($tenant, $scheduleA, [
            'counter_id' => $counter->id,
            'status' => TicketStatus::Waiting,
            'sequence' => 1,
        ]);

        $this->createTicket($tenant, $scheduleA, [
            'counter_id' => $counter->id,
            'status' => TicketStatus::Completed,
            'sequence' => 2,
            'completed_at' => now(),
        ]);

        $this->createTicket($tenant, $scheduleB, [
            'counter_id' => $counter->id,
            'status' => TicketStatus::Called,
            'called_at' => now(),
        ]);

        $this->selectCounterContext($owner, $tenant, $counter, $serviceA);

        $this->actingAs($owner)
            ->getJson(route('counter.snapshot', $tenant->id))
            ->assertOk()
            ->assertJsonPath('stats.waiting', 1)
            ->assertJsonPath('stats.completed', 1)
            ->assertJsonPath('stats.called', 0)
            ->assertJsonPath('stats.serving', 0);
    }

    public function test_counter_can_recall_active_ticket_without_changing_status(): void
    {
        config(['broadcasting.default' => 'null']);

        $owner = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
        ]);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_prefix' => 'CS',
        ]);
        $schedule = ServiceSchedule::factory()->create([
            'service_id' => $service->id,
            'day' => now()->dayOfWeekIso - 1,
        ]);
        $counter = Counter::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Counter A',
        ]);

        $ticket = $this->createTicket($tenant, $schedule, [
            'counter_id' => $counter->id,
            'status' => TicketStatus::Called,
            'called_at' => now(),
        ]);

        $this->selectCounterContext($owner, $tenant, $counter, $service);

        $this->actingAs($owner)
            ->postJson(route('counter.recall', $tenant->id))
            ->assertOk()
            ->assertJsonPath('ticket.queue_number', 'CS-1');

        $ticket->refresh();
        $this->assertSame(TicketStatus::Called, $ticket->status);
    }

    public function test_staff_only_sees_assigned_counters_and_cannot_select_unassigned_counter(): void
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
        ]);
        $tenant->users()->attach($staff->id, ['role' => 'staff']);

        $assignedCounter = Counter::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Counter A',
        ]);
        $blockedCounter = Counter::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Counter B',
        ]);
        $assignedCounter->staff()->attach($staff->id);

        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_prefix' => 'CS',
        ]);

        $this->actingAs($staff)
            ->getJson(route('counter.snapshot', $tenant->id))
            ->assertOk()
            ->assertJsonCount(1, 'counters')
            ->assertJsonPath('counters.0.id', $assignedCounter->id);

        $this->actingAs($staff)
            ->postJson(route('counter.context', $tenant->id), [
                'counter_id' => $blockedCounter->id,
                'service_id' => $service->id,
            ])
            ->assertForbidden();
    }

    private function selectCounterContext(User $user, Tenant $tenant, Counter $counter, Service $service): void
    {
        $this->actingAs($user)
            ->postJson(route('counter.context', $tenant->id), [
                'counter_id' => $counter->id,
                'service_id' => $service->id,
            ])
            ->assertOk();
    }

    private function createTicket(Tenant $tenant, ServiceSchedule $schedule, array $attributes = []): Ticket
    {
        return Ticket::create([
            'user_id' => null,
            'tenant_id' => $tenant->id,
            'service_schedule_id' => $schedule->id,
            'status' => TicketStatus::Waiting,
            'sequence' => 1,
            'service_date' => today()->toDateString(),
            ...$attributes,
        ]);
    }
}
