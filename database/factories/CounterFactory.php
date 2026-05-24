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

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Counter '.fake()->unique()->bothify('?#'),
            'is_active' => true,
        ];
    }
}
