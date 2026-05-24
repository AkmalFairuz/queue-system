<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_staff_to_counter(): void
    {
        $owner = User::factory()->create();
        $staffA = User::factory()->create();
        $staffB = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $tenant->users()->attach($staffA->id, ['role' => 'staff']);
        $tenant->users()->attach($staffB->id, ['role' => 'staff']);

        $response = $this->actingAs($owner)
            ->postJson(route('admin.counters.store', $tenant->id), [
                'name' => 'Counter A',
                'is_active' => true,
                'staff_ids' => [$staffA->id],
            ]);

        $response->assertOk();

        $counter = $tenant->counters()->where('name', 'Counter A')->firstOrFail();

        $this->assertDatabaseHas('counter_staff', [
            'counter_id' => $counter->id,
            'staff_id' => $staffA->id,
        ]);

        $this->actingAs($owner)
            ->putJson(route('admin.counters.update', [$tenant->id, $counter->id]), [
                'name' => 'Counter A',
                'is_active' => true,
                'staff_ids' => [$staffB->id],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('counter_staff', [
            'counter_id' => $counter->id,
            'staff_id' => $staffA->id,
        ]);

        $this->assertDatabaseHas('counter_staff', [
            'counter_id' => $counter->id,
            'staff_id' => $staffB->id,
        ]);
    }
}
