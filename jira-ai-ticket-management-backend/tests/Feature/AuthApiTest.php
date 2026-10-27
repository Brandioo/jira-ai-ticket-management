<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_as_employee(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'employee')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'role' => 'employee']);
    }

    public function test_user_can_login_and_read_profile(): void
    {
        $user = User::query()->create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::query()->create([
            'name' => 'Employee',
            'email' => 'employee@example.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'employee@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_protected_route_requires_token(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }
}
