<?php

namespace Tests\Feature;

use App\Models\Counter;
use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_display_snapshot_includes_latest_called_ticket_for_each_service(): void
    {
        $tenant = Tenant::factory()->create(['code' => 'display-test']);
        $counter = Counter::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Counter A',
        ]);

        $serviceA = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Customer Service',
            'ticket_prefix' => 'CS',
        ]);
        $scheduleA = ServiceSchedule::factory()->create([
            'service_id' => $serviceA->id,
        ]);

        $serviceB = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Informasi',
            'ticket_prefix' => 'IN',
        ]);
        $scheduleB = ServiceSchedule::factory()->create([
            'service_id' => $serviceB->id,
        ]);

        Ticket::create([
            'user_id' => null,
            'tenant_id' => $tenant->id,
            'service_schedule_id' => $scheduleA->id,
            'counter_id' => $counter->id,
            'status' => TicketStatus::Completed,
            'sequence' => 1,
            'service_date' => today()->toDateString(),
            'called_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(8),
        ]);

        Ticket::create([
            'user_id' => null,
            'tenant_id' => $tenant->id,
            'service_schedule_id' => $scheduleA->id,
            'counter_id' => $counter->id,
            'status' => TicketStatus::Serving,
            'sequence' => 3,
            'service_date' => today()->toDateString(),
            'called_at' => now()->subMinutes(2),
            'serving_started_at' => now()->subMinute(),
        ]);

        Ticket::create([
            'user_id' => null,
            'tenant_id' => $tenant->id,
            'service_schedule_id' => $scheduleB->id,
            'counter_id' => $counter->id,
            'status' => TicketStatus::Called,
            'sequence' => 2,
            'service_date' => today()->toDateString(),
            'called_at' => now()->subMinutes(5),
        ]);

        $response = $this->getJson(route('tenant.display.snapshot', $tenant->code));

        $response->assertOk()
            ->assertJsonPath('services.0.name', 'Customer Service')
            ->assertJsonPath('services.0.stats.completed', 1)
            ->assertJsonPath('services.0.stats.serving', 1)
            ->assertJsonPath('services.0.last_called_ticket.queue_number', 'CS-3')
            ->assertJsonPath('services.1.name', 'Informasi')
            ->assertJsonPath('services.1.stats.called', 1)
            ->assertJsonPath('services.1.last_called_ticket.queue_number', 'IN-2');
    }
}
