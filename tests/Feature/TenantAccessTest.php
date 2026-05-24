<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_tenant_access_cannot_open_admin_page(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $this->actingAs($intruder)
            ->get(route('admin.show', $tenant->id))
            ->assertForbidden();
    }
}
