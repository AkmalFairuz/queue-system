<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_user_can_take_ticket_from_open_service(): void
    {
        config(['broadcasting.default' => 'null']);

        $tenant = Tenant::factory()->create(['code' => 'publik']);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_prefix' => 'CS',
        ]);
        $schedule = ServiceSchedule::factory()->create([
            'service_id' => $service->id,
            'day' => now()->dayOfWeekIso - 1,
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:59',
        ]);

        $response = $this->takeTicket($tenant, $service, $schedule);

        $response->assertOk()
            ->assertJsonPath('ticket.queue_number', 'CS-1')
            ->assertJsonPath('redirect_url', route('tenant.queue.ticket', [$tenant->code, 1]));

        $this->assertDatabaseHas('tickets', [
            'tenant_id' => $tenant->id,
            'status' => TicketStatus::Waiting->value,
            'sequence' => 1,
        ]);
    }

    public function test_public_user_can_open_selected_service_queue_page(): void
    {
        $tenant = Tenant::factory()->create(['code' => 'detail-layanan']);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Customer Service',
        ]);
        $schedule = ServiceSchedule::factory()->create([
            'service_id' => $service->id,
            'day' => now()->dayOfWeekIso - 1,
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:59',
            'max_tickets' => 5,
        ]);

        Ticket::create([
            'tenant_id' => $tenant->id,
            'service_schedule_id' => $schedule->id,
            'status' => TicketStatus::Waiting,
            'sequence' => 1,
            'service_date' => today()->toDateString(),
        ]);

        $response = $this->get(route('tenant.queue.service', [$tenant->code, $service->id]));

        $response->assertOk()
            ->assertSee('Customer Service')
            ->assertSee('Memuat detail jadwal layanan.')
            ->assertSee('date_options')
            ->assertSee('"remaining_quota":4', false);
    }

    public function test_public_user_can_open_created_ticket_result_page(): void
    {
        $tenant = Tenant::factory()->create(['code' => 'hasil-tiket']);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Customer Service',
            'ticket_prefix' => 'CS',
        ]);
        $schedule = ServiceSchedule::factory()->create([
            'service_id' => $service->id,
            'day' => now()->dayOfWeekIso - 1,
            'opens_at' => '08:00:00',
            'closes_at' => '16:00:00',
        ]);

        $createdTicket = Ticket::create([
            'tenant_id' => $tenant->id,
            'service_schedule_id' => $schedule->id,
            'status' => TicketStatus::Waiting,
            'sequence' => 2,
            'service_date' => today()->toDateString(),
        ]);
        Ticket::create([
            'tenant_id' => $tenant->id,
            'service_schedule_id' => $schedule->id,
            'status' => TicketStatus::Called,
            'sequence' => 1,
            'service_date' => today()->toDateString(),
            'called_at' => now(),
        ]);

        $response = $this->get(route('tenant.queue.ticket', [$tenant->code, $createdTicket->id]));

        $response->assertOk()
            ->assertSee('Tiket Antrian Anda')
            ->assertSee('CS-2')
            ->assertSee('Customer Service')
            ->assertSee('CS-1')
            ->assertSee('queue-ticket-result-payload');
    }

    public function test_ticket_request_fails_when_daily_quota_is_exhausted(): void
    {
        config(['broadcasting.default' => 'null']);

        $tenant = Tenant::factory()->create(['code' => 'kuota']);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_prefix' => 'IN',
        ]);
        $schedule = ServiceSchedule::factory()->create([
            'service_id' => $service->id,
            'day' => now()->dayOfWeekIso - 1,
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:59',
            'max_tickets' => 1,
        ]);

        Ticket::create([
            'user_id' => null,
            'tenant_id' => $tenant->id,
            'service_schedule_id' => $schedule->id,
            'status' => TicketStatus::Waiting,
            'sequence' => 1,
            'service_date' => today()->toDateString(),
        ]);

        $response = $this->takeTicket($tenant, $service, $schedule);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('service_schedule_id');
    }

    public function test_public_user_can_take_ticket_for_tomorrows_schedule_during_pre_queue_window(): void
    {
        config(['broadcasting.default' => 'null']);

        $this->travelTo(Carbon::create(2026, 5, 24, 10, 0, 0));

        $tenant = Tenant::factory()->create(['code' => 'pra-antrian']);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_prefix' => 'CS',
        ]);
        $schedule = ServiceSchedule::factory()->create([
            'service_id' => $service->id,
            'day' => 0,
            'opens_at' => '09:00:00',
            'closes_at' => '17:00:00',
            'pre_queue_minutes' => 1440,
        ]);

        $response = $this->takeTicket($tenant, $service, $schedule);

        $response->assertOk()
            ->assertJsonPath('ticket.queue_number', 'CS-1');

        $this->assertDatabaseHas('tickets', [
            'tenant_id' => $tenant->id,
            'service_date' => '2026-05-25 00:00:00',
            'status' => TicketStatus::Waiting->value,
            'sequence' => 1,
        ]);

        $this->travelBack();
    }

    private function takeTicket(Tenant $tenant, Service $service, ServiceSchedule $schedule)
    {
        return $this->postJson(route('tenant.queue.store', $tenant->code), [
            'service_id' => $service->id,
            'service_schedule_id' => $schedule->id,
        ]);
    }
}
