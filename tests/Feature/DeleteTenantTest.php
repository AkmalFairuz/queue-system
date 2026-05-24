<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_tenant(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $response = $this->actingAs($owner)
            ->deleteJson(route('tenants.destroy', $tenant->id));

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Tenant berhasil dihapus.',
                'redirect_url' => route('home'),
            ]);

        $this->assertDatabaseMissing('tenants', [
            'id' => $tenant->id,
        ]);
    }

    public function test_tenant_admin_cannot_delete_tenant(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $tenant->users()->attach($admin->id, ['role' => 'admin']);

        $this->actingAs($admin)
            ->deleteJson(route('tenants.destroy', $tenant->id))
            ->assertForbidden();

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
        ]);
    }

    public function test_guest_cannot_delete_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $this->delete(route('tenants.destroy', $tenant->id))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
        ]);
    }
}
