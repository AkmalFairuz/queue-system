<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Customer Service', 'Informasi', 'Kasir']).' '.fake()->unique()->numberBetween(1, 999),
            'ticket_prefix' => fake()->unique()->bothify('??#'),
            'is_login_required' => false,
        ];
    }
}
