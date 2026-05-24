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
                'Loket 1',
                'Loket 2',
                'Loket 3',
                'Loket 4',
            ]),
            'is_active' => true,
        ];
    }
}
