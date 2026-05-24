<?php

namespace Database\Seeders;

use App\Models\Counter;
use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TicketStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Pemilik Tenant',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
        ]);

        $admin = User::factory()->create([
            'name' => 'Petugas Counter',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $tenant = Tenant::factory()->create([
            'name' => 'AOL Queue Demo',
            'code' => 'aol-demo',
            'owner_id' => $owner->id,
            'tts_language' => 'id-ID',
            'tts_template' => 'Nomor antrian {queue}, silakan menuju {counter}',
        ]);

        $tenant->users()->attach($admin->id, ['role' => 'admin']);

        $serviceA = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Customer Service',
            'ticket_prefix' => 'CS',
        ]);

        $serviceB = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Informasi',
            'ticket_prefix' => 'IN',
        ]);

        $scheduleA = ServiceSchedule::factory()->create([
            'service_id' => $serviceA->id,
            'day' => now()->dayOfWeekIso - 1,
        ]);

        $scheduleB = ServiceSchedule::factory()->create([
            'service_id' => $serviceB->id,
            'day' => now()->dayOfWeekIso - 1,
        ]);

        Counter::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Counter A',
        ]);

        Counter::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Counter B',
        ]);

        Ticket::create([
            'user_id' => null,
            'tenant_id' => $tenant->id,
            'service_schedule_id' => $scheduleA->id,
            'status' => TicketStatus::Waiting,
            'sequence' => 1,
            'service_date' => today()->toDateString(),
        ]);

        Ticket::create([
            'user_id' => null,
            'tenant_id' => $tenant->id,
            'service_schedule_id' => $scheduleA->id,
            'status' => TicketStatus::Waiting,
            'sequence' => 2,
            'service_date' => today()->toDateString(),
        ]);

        Ticket::create([
            'user_id' => null,
            'tenant_id' => $tenant->id,
            'service_schedule_id' => $scheduleB->id,
            'status' => TicketStatus::Waiting,
            'sequence' => 1,
            'service_date' => today()->toDateString(),
        ]);
    }
}
