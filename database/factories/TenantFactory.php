<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => fake()->unique()->bothify('TENANT-###'),
            'tts_language' => 'id-ID',
            'tts_template' => 'Nomor antrian {queue}, silakan menuju {counter}',
            'owner_id' => User::factory(),
        ];
    }
}
