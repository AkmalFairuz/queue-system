<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceSchedule;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\TicketStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'user_id' => null,
            'tenant_id' => $tenant,
            'service_schedule_id' => ServiceSchedule::factory()->state(function () use ($tenant) {
                return [
                    'service_id' => Service::factory()->for($tenant),
                ];
            }),
            'counter_id' => null,
            'status' => TicketStatus::Waiting,
            'sequence' => fake()->numberBetween(1, 120),
            'service_date' => today()->toDateString(),
            'called_at' => null,
            'serving_started_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'skipped_at' => null,
        ];
    }
}
