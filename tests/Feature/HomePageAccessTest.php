<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_user_sees_tenant_on_home_page_and_can_access_counter_link(): void
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Tenant Staff',
            'code' => 'tenant-staff',
        ]);

        $tenant->users()->attach($staff->id, ['role' => 'staff']);

        $response = $this->actingAs($staff)->get(route('home'));

        $response->assertOk();
        $response->assertSee('Tenant Staff');
        $response->assertSee(route('counter.show', $tenant->id), false);
        $response->assertDontSee(route('admin.show', $tenant->id), false);
    }
}
