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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Direktur Rumah Sakit',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin Pendaftaran',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $staffA = User::factory()->create([
            'name' => 'Petugas Loket 1',
            'email' => 'staff1@example.com',
            'password' => Hash::make('password'),
        ]);

        $staffB = User::factory()->create([
            'name' => 'Petugas Loket 2',
            'email' => 'staff2@example.com',
            'password' => Hash::make('password'),
        ]);

        $tenant = Tenant::factory()->create([
            'name' => 'RS Harapan Sehat',
            'code' => 'rs-harapan-sehat',
            'owner_id' => $owner->id,
            'tts_language' => 'id-ID',
            'tts_template' => 'Nomor antrian {queue}, silakan menuju {counter}',
        ]);

        $tenant->users()->attach($admin->id, ['role' => 'admin']);
        $tenant->users()->attach($staffA->id, ['role' => 'staff']);
        $tenant->users()->attach($staffB->id, ['role' => 'staff']);

        $serviceA = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Poli Umum',
            'ticket_prefix' => 'U',
        ]);

        $serviceB = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Poli Gigi',
            'ticket_prefix' => 'G',
        ]);

        $serviceC = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Poli Anak',
            'ticket_prefix' => 'A',
        ]);

        $scheduleA = $this->seedWeekdaySchedules($serviceA, '08:00:00', '17:00:00', 40)->first();
        $scheduleB = $this->seedWeekdaySchedules($serviceB, '09:00:00', '17:00:00', 25)->first();
        $scheduleC = $this->seedWeekdaySchedules($serviceC, '08:30:00', '15:30:00', 20)->first();

        $counterA = Counter::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Loket 1',
        ]);

        $counterB = Counter::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Loket 2',
        ]);

        $counterA->staff()->attach($staffA->id);
        $counterB->staff()->attach($staffB->id);

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

        Ticket::create([
            'user_id' => null,
            'tenant_id' => $tenant->id,
            'service_schedule_id' => $scheduleC->id,
            'status' => TicketStatus::Waiting,
            'sequence' => 1,
            'service_date' => today()->toDateString(),
        ]);
    }

    private function seedWeekdaySchedules(
        Service $service,
        string $opensAt,
        string $closesAt,
        int $maxTickets,
    ): Collection {
        return collect(range(0, 4))->map(function (int $day) use ($service, $opensAt, $closesAt, $maxTickets) {
            return ServiceSchedule::factory()->create([
                'service_id' => $service->id,
                'day' => $day,
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
                'max_tickets' => $maxTickets,
            ]);
        });
    }
}
