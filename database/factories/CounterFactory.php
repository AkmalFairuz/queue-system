<?php

namespace Database\Factories;

use App\Models\Counter;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Counter>
 */
class CounterFactory extends Factory
{
    protected $model = Counter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement([
                'Loket Pendaftaran 1',
                'Loket Pendaftaran 2',
                'Loket BPJS',
                'Loket Rawat Jalan',
            ]),
            'is_active' => true,
        ];
    }
}
