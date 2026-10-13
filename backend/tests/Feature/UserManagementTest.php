<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivating_a_user_revokes_their_existing_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $storekeeper = User::factory()->create(['role' => 'storekeeper']);
        $storekeeperToken = $storekeeper->createToken('t')->plainTextToken;

        // The storekeeper can authenticate before deactivation.
        $this->withHeader('Authorization', "Bearer $storekeeperToken")
            ->getJson('/api/v1/auth/me')->assertOk();

        // Re-resolve the guard so the requests below are authenticated fresh.
        $this->app['auth']->forgetGuards();

        $adminToken = $admin->createToken('t')->plainTextToken;
        $this->withHeader('Authorization', "Bearer $adminToken")
            ->putJson("/api/v1/users/{$storekeeper->id}", ['is_active' => false])
            ->assertOk();

        $this->assertSame(0, $storekeeper->tokens()->count());

        // Re-resolve the guard so the previously authenticated user is not cached.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer $storekeeperToken")
            ->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_a_user_cannot_deactivate_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $token = $admin->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/v1/users/{$admin->id}")
            ->assertStatus(422);

        $this->assertTrue($admin->fresh()->is_active);
    }
}
