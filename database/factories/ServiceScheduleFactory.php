<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceSchedule>
 */
class ServiceScheduleFactory extends Factory
{
    protected $model = ServiceSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'day' => fake()->numberBetween(0, 4),
            'opens_at' => '08:00:00',
            'closes_at' => '17:00:00',
            'pre_queue_minutes' => 0,
            'max_tickets' => fake()->numberBetween(10, 50),
            'is_available' => true,
        ];
    }
}
