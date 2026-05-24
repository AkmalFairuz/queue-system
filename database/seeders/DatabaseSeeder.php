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
        $owner = $this->upsertDemoUser('owner@example.com');
        $admin = $this->upsertDemoUser('admin@example.com');
        $staffA = $this->upsertDemoUser('staff1@example.com');
        $staffB = $this->upsertDemoUser('staff2@example.com');

        $tenant = $this->upsertDemoTenant($owner);

        $tenant->users()->syncWithoutDetaching([
            $admin->id => ['role' => 'admin'],
            $staffA->id => ['role' => 'staff'],
            $staffB->id => ['role' => 'staff'],
        ]);

        $this->resetDemoTenantData($tenant);

        $services = $this->seedUniqueServices($tenant, 3);

        $serviceA = $services[0];
        $serviceB = $services[1];
        $serviceC = $services[2];

        $scheduleA = $this->seedWeekdaySchedules($serviceA, '08:00:00', '17:00:00', 40)->first();
        $scheduleB = $this->seedWeekdaySchedules($serviceB, '09:00:00', '17:00:00', 25)->first();
        $scheduleC = $this->seedWeekdaySchedules($serviceC, '08:30:00', '15:30:00', 20)->first();

        $counters = $this->seedUniqueCounters($tenant, 2);

        $counterA = $counters[0];
        $counterB = $counters[1];

        $counterA->staff()->syncWithoutDetaching([$staffA->id]);
        $counterB->staff()->syncWithoutDetaching([$staffB->id]);

        $this->seedWaitingTickets($tenant->id, [
            [$scheduleA, 1],
            [$scheduleA, 2],
            [$scheduleB, 1],
            [$scheduleC, 1],
        ]);
    }

    private function upsertDemoUser(string $email): User
    {
        $attributes = User::factory()->make([
            'email' => $email,
            'password' => Hash::make('password'),
        ])->getAttributes();

        return User::query()->updateOrCreate([
            'email' => $email,
        ], $attributes);
    }

    private function upsertDemoTenant(User $owner): Tenant
    {
        $attributes = Tenant::factory()->make([
            'code' => 'rs-harapan-sehat',
            'owner_id' => $owner->id,
        ])->getAttributes();

        return Tenant::query()->updateOrCreate([
            'code' => 'rs-harapan-sehat',
        ], $attributes);
    }

    private function resetDemoTenantData(Tenant $tenant): void
    {
        $tenant->tickets()->delete();

        $tenant->counters->each(function (Counter $counter): void {
            $counter->staff()->detach();
        });
        $tenant->counters()->delete();

        $tenant->services()->delete();
    }

    /**
     * @return Collection<int, Service>
     */
    private function seedUniqueServices(Tenant $tenant, int $count): Collection
    {
        $services = collect();
        $usedPrefixes = [];

        while ($services->count() < $count) {
            $service = Service::factory()->make(['tenant_id' => $tenant->id]);

            if (in_array($service->ticket_prefix, $usedPrefixes, true)) {
                continue;
            }

            $services->push(Service::query()->create($service->getAttributes()));
            $usedPrefixes[] = $service->ticket_prefix;
        }

        return $services;
    }

    /**
     * @return Collection<int, Counter>
     */
    private function seedUniqueCounters(Tenant $tenant, int $count): Collection
    {
        $counters = collect();
        $usedNames = [];

        while ($counters->count() < $count) {
            $counter = Counter::factory()->make(['tenant_id' => $tenant->id]);

            if (in_array($counter->name, $usedNames, true)) {
                continue;
            }

            $counters->push(Counter::query()->create($counter->getAttributes()));
            $usedNames[] = $counter->name;
        }

        return $counters;
    }

    private function seedWeekdaySchedules(
        Service $service,
        string $opensAt,
        string $closesAt,
        int $maxTickets,
    ): Collection {
        return collect(range(0, 4))->map(function (int $day) use ($service, $opensAt, $closesAt, $maxTickets) {
            return ServiceSchedule::query()->updateOrCreate([
                'service_id' => $service->id,
                'day' => $day,
                'opens_at' => $opensAt,
            ], [
                'closes_at' => $closesAt,
                'max_tickets' => $maxTickets,
            ]);
        });
    }

    /**
     * @param  array<int, array{0: ServiceSchedule, 1: int}>  $tickets
     */
    private function seedWaitingTickets(int $tenantId, array $tickets): void
    {
        foreach ($tickets as [$schedule, $sequence]) {
            Ticket::query()->create([
                'user_id' => null,
                'tenant_id' => $tenantId,
                'service_schedule_id' => $schedule->id,
                'status' => TicketStatus::Waiting,
                'sequence' => $sequence,
                'service_date' => today()->toDateString(),
            ]);
        }
    }
}
