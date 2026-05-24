<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_account(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Akmal',
            'email' => 'akmal@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('home'));

        $this->assertDatabaseHas('users', [
            'name' => 'Akmal',
            'email' => 'akmal@example.com',
        ]);

        $this->assertAuthenticated();
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create([
            'email' => 'taken@example.com',
        ]);

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Akmal',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('email');
    }
}
