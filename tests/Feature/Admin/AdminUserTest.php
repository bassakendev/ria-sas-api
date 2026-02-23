<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = User::factory()->superadmin()->create();
        $this->token = $this->superadmin->createToken('api-token')->plainTextToken;
    }

    /**
     * Test superadmin can list all users.
     */
    public function test_superadmin_can_list_users(): void
    {
        User::factory()->count(10)->create();

        $response = $this->getJson('/api/admin/users', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'users' => [
                    '*' => [
                        'id',
                        'email',
                        'role',
                        'status',
                        'plan',
                        'lastLoginAt',
                        'createdAt',
                    ],
                ],
                'page',
                'total',
            ]);
    }

    /**
     * Test users list can be filtered by role.
     */
    public function test_users_list_can_be_filtered_by_role(): void
    {
        User::factory()->count(3)->create(['role' => 'user']);
        User::factory()->admin()->count(2)->create();

        $response = $this->getJson('/api/admin/users?role=admin', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('total'));
    }

    /**
     * Test users list can be filtered by status.
     */
    public function test_users_list_can_be_filtered_by_status(): void
    {
        User::factory()->count(3)->create(['status' => 'active']);
        User::factory()->count(2)->create(['status' => 'suspended']);

        $response = $this->getJson('/api/admin/users?status=suspended', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('total'));
    }

    /**
     * Test users list can be searched by query.
     */
    public function test_users_list_can_be_searched(): void
    {
        User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        User::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
        ]);

        $response = $this->getJson('/api/admin/users?query=john', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
    }

    /**
     * Test superadmin can show single user.
     */
    public function test_superadmin_can_show_user(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/admin/users/{$user->id}", [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => 'usr_' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                'email' => $user->email,
            ]);
    }

    /**
     * Test superadmin can change user role.
     */
    public function test_superadmin_can_change_user_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->patchJson("/api/admin/users/{$user->id}/role", [
            'role' => 'admin',
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'role' => 'admin',
                'message' => 'User role updated successfully',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'admin',
        ]);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->superadmin->id,
            'action' => 'CHANGE_ROLE',
            'target' => 'usr_' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Test role change requires valid role.
     */
    public function test_role_change_requires_valid_role(): void
    {
        $user = User::factory()->create();

        $response = $this->patchJson("/api/admin/users/{$user->id}/role", [
            'role' => 'invalid',
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    /**
     * Test superadmin can suspend user.
     */
    public function test_superadmin_can_suspend_user(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->postJson("/api/admin/users/{$user->id}/suspend", [], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'suspended',
                'message' => 'User suspended successfully',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'suspended',
        ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'SUSPEND_USER',
        ]);
    }

    /**
     * Test superadmin can activate user.
     */
    public function test_superadmin_can_activate_user(): void
    {
        $user = User::factory()->create(['status' => 'suspended']);

        $response = $this->postJson("/api/admin/users/{$user->id}/activate", [], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'active',
                'message' => 'User activated successfully',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test superadmin can delete user.
     */
    public function test_superadmin_can_delete_user(): void
    {
        $user = User::factory()->create();
        $userId = $user->id;

        $response = $this->deleteJson("/api/admin/users/{$user->id}", [], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'User deleted successfully',
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $userId,
        ]);

        // Verify audit log contains user email
        $auditLog = AuditLog::where('action', 'DELETE_USER')->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals($user->email, $auditLog->metadata['email']);
    }

    /**
     * Test superadmin can assign plan to user.
     */
    public function test_superadmin_can_assign_plan_to_user(): void
    {
        $user = User::factory()->create([
            'subscription_plan' => 'free',
            'subscription_status' => 'active',
        ]);

        $response = $this->postJson("/api/admin/users/{$user->id}/assign-plan", [
            'plan' => 'pro',
            'reason' => 'Promotional upgrade',
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'subscription_plan' => 'pro',
                'message' => 'Plan assigned successfully',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'subscription_plan' => 'pro',
        ]);

        // Verify subscription was created or updated
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan' => 'pro',
            'status' => 'active',
        ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ASSIGN_PLAN',
        ]);
    }

    /**
     * Test plan assignment requires valid plan.
     */
    public function test_plan_assignment_requires_valid_plan(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson("/api/admin/users/{$user->id}/assign-plan", [
            'plan' => 'invalid',
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }

    /**
     * Test regular user cannot access admin user endpoints.
     */
    public function test_regular_user_cannot_access_admin_user_endpoints(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson('/api/admin/users', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }
}
