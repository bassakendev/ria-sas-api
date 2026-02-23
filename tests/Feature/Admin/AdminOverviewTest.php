<?php

namespace Tests\Feature\Admin;

use App\Models\Feedback;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOverviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test superadmin can access overview.
     */
    public function test_superadmin_can_access_overview(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $token = $superadmin->createToken('api-token')->plainTextToken;

        // Create some test data
        User::factory()->count(5)->create();
        Subscription::factory()->pro()->active()->count(3)->create([
            'user_id' => User::factory()->create()->id,
        ]);
        Feedback::factory()->count(2)->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'new',
        ]);

        $response = $this->getJson('/api/admin/overview', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'metrics' => [
                    'usersTotal',
                    'usersActive',
                    'mrr',
                    'churnRate',
                    'pendingFeedbacks',
                    'openTickets',
                ],
                'subscriptions' => [
                    'free',
                    'pro',
                    'trial',
                ],
                'support' => [
                    'avgResponseTimeHours',
                    'slaBreaches',
                ],
                'system' => [
                    'status',
                    'lastBackupAt',
                    'queueDepth',
                ],
                'recentActivity',
                'alerts',
            ]);
    }

    /**
     * Test regular user cannot access overview.
     */
    public function test_regular_user_cannot_access_overview(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson('/api/admin/overview', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'forbidden',
                'code' => 'ROLE_NOT_ALLOWED',
            ]);
    }

    /**
     * Test admin user cannot access overview.
     */
    public function test_admin_cannot_access_overview(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('api-token')->plainTextToken;

        $response = $this->getJson('/api/admin/overview', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated user cannot access overview.
     */
    public function test_unauthenticated_cannot_access_overview(): void
    {
        $response = $this->getJson('/api/admin/overview');

        $response->assertStatus(401);
    }
}
