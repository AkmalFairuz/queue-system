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

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $services = [
            ['name' => 'Poli Umum', 'prefix' => 'U'],
            ['name' => 'Poli Gigi', 'prefix' => 'G'],
            ['name' => 'Poli Anak', 'prefix' => 'A'],
            ['name' => 'Poli Mata', 'prefix' => 'M'],
            ['name' => 'Poli Jantung', 'prefix' => 'J'],
        ];

        $service = fake()->randomElement($services);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $service['name'],
            'ticket_prefix' => $service['prefix'],
            'is_login_required' => false,
        ];
    }
}
