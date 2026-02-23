<?php

namespace Tests\Feature\Admin;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSubscriptionTest extends TestCase
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
     * Test superadmin can list all subscriptions.
     */
    public function test_superadmin_can_list_subscriptions(): void
    {
        $users = User::factory()->count(5)->create();
        foreach ($users as $user) {
            Subscription::factory()->create(['user_id' => $user->id]);
        }

        $response = $this->getJson('/api/admin/subscriptions', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'subscriptions' => [
                    '*' => [
                        'id',
                        'userId',
                        'plan',
                        'status',
                        'startDate',
                        'nextBillingDate',
                        'canceledAt',
                    ],
                ],
                'page',
                'total',
            ]);
    }

    /**
     * Test subscriptions list can be filtered by plan.
     */
    public function test_subscriptions_list_can_be_filtered_by_plan(): void
    {
        Subscription::factory()->pro()->count(3)->create([
            'user_id' => User::factory()->create()->id,
        ]);
        Subscription::factory()->free()->count(2)->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->getJson('/api/admin/subscriptions?plan=pro', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('total'));
    }

    /**
     * Test subscriptions list can be filtered by status.
     */
    public function test_subscriptions_list_can_be_filtered_by_status(): void
    {
        Subscription::factory()->active()->count(3)->create([
            'user_id' => User::factory()->create()->id,
        ]);
        Subscription::factory()->canceled()->count(2)->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->getJson('/api/admin/subscriptions?status=canceled', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('total'));
    }

    /**
     * Test superadmin can change subscription plan.
     */
    public function test_superadmin_can_change_subscription_plan(): void
    {
        $user = User::factory()->create([
            'subscription_plan' => 'free',
            'subscription_status' => 'active',
        ]);
        $subscription = Subscription::factory()->free()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->patchJson("/api/admin/subscriptions/{$subscription->id}/plan", [
            'plan' => 'pro',
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'plan' => 'pro',
                'message' => 'Subscription plan updated successfully',
            ]);

        // Verify subscription was updated
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'plan' => 'pro',
        ]);

        // Verify user was updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'subscription_plan' => 'pro',
        ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'CHANGE_SUBSCRIPTION_PLAN',
        ]);
    }

    /**
     * Test plan change requires valid plan.
     */
    public function test_plan_change_requires_valid_plan(): void
    {
        $subscription = Subscription::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->patchJson("/api/admin/subscriptions/{$subscription->id}/plan", [
            'plan' => 'invalid',
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }

    /**
     * Test superadmin can cancel subscription.
     */
    public function test_superadmin_can_cancel_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->active()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->postJson("/api/admin/subscriptions/{$subscription->id}/cancel", [
            'reason' => 'Admin cancellation for testing',
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'canceled',
                'message' => 'Subscription canceled successfully',
            ]);

        // Verify subscription was canceled
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'canceled',
        ]);

        // Verify audit log
        $auditLog = \App\Models\AuditLog::where('action', 'CANCEL_SUBSCRIPTION')->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals('Admin cancellation for testing', $auditLog->metadata['reason']);
    }

    /**
     * Test subscription cancellation requires reason.
     */
    public function test_subscription_cancellation_requires_reason(): void
    {
        $subscription = Subscription::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->postJson("/api/admin/subscriptions/{$subscription->id}/cancel", [], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    /**
     * Test regular user cannot access admin subscription endpoints.
     */
    public function test_regular_user_cannot_access_admin_subscription_endpoints(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson('/api/admin/subscriptions', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }
}
