<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_are_seeded_with_working_credentials(): void
    {
        $this->seed(DatabaseSeeder::class);

        $accounts = [
            'admin@example.com' => 'admin',
            'manager@example.com' => 'manager',
            'alice@example.com' => 'employee',
            'bob@example.com' => 'employee',
        ];

        foreach ($accounts as $email => $role) {
            $user = User::query()->where('email', $email)->first();

            $this->assertNotNull($user, "Expected seeded user {$email} to exist.");
            $this->assertSame($role, $user->role);
            $this->assertTrue(Hash::check('password', $user->password), "Seeded password failed for {$email}.");
        }
    }
}
