<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'RS Harapan Sehat',
            'RS Sejahtera Medika',
            'Klinik Keluarga Sentosa',
            'RS Mitra Ibu',
        ]);

        return [
            'name' => $name,
            'code' => Str::slug($name.'-'.fake()->unique()->numberBetween(1, 999)),
            'tts_language' => 'id-ID',
            'tts_template' => 'Nomor antrian {queue}, silakan menuju {counter}',
            'owner_id' => User::factory(),
        ];
    }
}
