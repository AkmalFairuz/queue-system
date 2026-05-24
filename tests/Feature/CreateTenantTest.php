<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_tenant_and_become_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('tenants.store'), [
            'name' => 'Tenant Baru',
            'code' => 'tenant-baru',
            'tts_language' => 'id-ID',
            'tts_template' => 'Nomor antrian {queue}, silakan menuju {counter}',
        ]);

        $tenant = \App\Models\Tenant::where('code', 'tenant-baru')->firstOrFail();

        $response->assertRedirect(route('admin.show', $tenant->id));

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Tenant Baru',
            'owner_id' => $user->id,
        ]);
    }

    public function test_guest_cannot_create_tenant(): void
    {
        $this->post(route('tenants.store'), [
            'name' => 'Tenant Baru',
            'code' => 'tenant-baru',
            'tts_language' => 'id-ID',
            'tts_template' => 'Nomor antrian {queue}, silakan menuju {counter}',
        ])->assertRedirect(route('login'));
    }
}
