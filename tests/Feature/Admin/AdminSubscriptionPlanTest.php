<?php

namespace Tests\Feature\Admin;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $seeder = new SubscriptionPlanSeeder();
        $seeder->run();
    }

    /**
     * Test: List all subscription plans as superadmin.
     */
    public function test_superadmin_can_list_subscription_plans(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($superadmin)
            ->getJson('/api/admin/plans');

        $response->assertStatus(200);
        $response->assertJsonCount(2); // free and pro plans
        $response->assertJsonStructure([
            '*' => [
                'id',
                'code',
                'name',
                'price',
                'currency',
                'interval',
                'features',
                'limits',
                'createdAt',
                'updatedAt',
            ],
        ]);

        // Verify both plans are present
        $this->assertContains('free', $response->json('*.code'));
        $this->assertContains('pro', $response->json('*.code'));
    }

    /**
     * Test: List plans without authentication should fail.
     */
    public function test_unauthenticated_user_cannot_list_plans(): void
    {
        $response = $this->getJson('/api/admin/plans');

        $response->assertStatus(401);
    }

    /**
     * Test: Regular user cannot access plan list.
     */
    public function test_regular_user_cannot_list_plans(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)
            ->getJson('/api/admin/plans');

        $response->assertStatus(403); // Forbidden - not superadmin
    }

    /**
     * Test: View specific subscription plan as superadmin.
     */
    public function test_superadmin_can_view_specific_plan(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $plan = SubscriptionPlan::where('code', 'free')->first();

        $response = $this->actingAs($superadmin)
            ->getJson("/api/admin/plans/{$plan->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $plan->id,
            'code' => 'free',
            'name' => 'Plan Gratuit',
            'price' => 0.0,
            'currency' => 'EUR',
        ]);
    }

    /**
     * Test: View non-existent plan returns 404.
     */
    public function test_view_nonexistent_plan_returns_404(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($superadmin)
            ->getJson('/api/admin/plans/99999');

        $response->assertStatus(404);
    }

    /**
     * Test: Superadmin can update plan name.
     */
    public function test_superadmin_can_update_plan_name(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $plan = SubscriptionPlan::where('code', 'free')->first();

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/admin/plans/{$plan->id}", [
                'name' => 'Basic Plan',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'name' => 'Basic Plan',
            'message' => 'Plan updated successfully',
        ]);

        // Verify database was updated
        $this->assertDatabaseHas('subscription_plans', [
            'id' => $plan->id,
            'name' => 'Basic Plan',
        ]);
    }

    /**
     * Test: Superadmin can update plan features.
     */
    public function test_superadmin_can_update_plan_features(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $plan = SubscriptionPlan::where('code', 'free')->first();

        $newFeatures = [
            'Up to 10 invoices per month',
            'Up to 5 clients',
            'Email support',
        ];

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/admin/plans/{$plan->id}", [
                'features' => $newFeatures,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'features' => $newFeatures,
            'message' => 'Plan updated successfully',
        ]);

        // Verify database was updated
        $this->assertDatabaseHas('subscription_plans', [
            'id' => $plan->id,
        ]);

        $updatedPlan = SubscriptionPlan::find($plan->id);
        $this->assertEquals($newFeatures, $updatedPlan->features);
    }

    /**
     * Test: Superadmin can update plan limits.
     */
    public function test_superadmin_can_update_plan_limits(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $plan = SubscriptionPlan::where('code', 'pro')->first();

        $newLimits = [
            'invoicesPerMonth' => 500,
            'clients' => 100,
            'storage' => '50GB',
            'support' => 'priority',
        ];

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/admin/plans/{$plan->id}", [
                'limits' => $newLimits,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'limits' => $newLimits,
        ]);

        // Verify database was updated
        $updatedPlan = SubscriptionPlan::find($plan->id);
        $this->assertEquals($newLimits, $updatedPlan->limits);
    }

    /**
     * Test: Superadmin can update multiple fields at once.
     */
    public function test_superadmin_can_update_multiple_fields(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $plan = SubscriptionPlan::where('code', 'free')->first();

        $newFeatures = ['Feature 1', 'Feature 2'];
        $newLimits = ['limit1' => 10, 'limit2' => 20];

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/admin/plans/{$plan->id}", [
                'name' => 'Updated Free Plan',
                'features' => $newFeatures,
                'limits' => $newLimits,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'name' => 'Updated Free Plan',
            'features' => $newFeatures,
            'limits' => $newLimits,
            'changesApplied' => 3, // 3 changes made
        ]);

        // Verify all changes in database
        $updatedPlan = SubscriptionPlan::find($plan->id);
        $this->assertEquals('Updated Free Plan', $updatedPlan->name);
        $this->assertEquals($newFeatures, $updatedPlan->features);
        $this->assertEquals($newLimits, $updatedPlan->limits);
    }

    /**
     * Test: Update with no changes returns appropriate response.
     */
    public function test_update_with_no_changes(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $plan = SubscriptionPlan::where('code', 'free')->first();
        $originalName = $plan->name;

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/admin/plans/{$plan->id}", [
                'name' => $originalName, // Same name
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Plan updated successfully',
            'changesApplied' => 'No changes',
        ]);
    }

    /**
     * Test: Cannot update plan code (price, currency, interval are protected).
     */
    public function test_cannot_modify_protected_fields(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $plan = SubscriptionPlan::where('code', 'free')->first();

        // Try to update price, currency, interval (not in validation, so they'll be ignored)
        $response = $this->actingAs($superadmin)
            ->patchJson("/api/admin/plans/{$plan->id}", [
                'price' => 999,
                'currency' => 'USD',
                'interval' => 'year',
            ]);

        $response->assertStatus(200);

        // Verify fields weren't changed
        $this->assertDatabaseHas('subscription_plans', [
            'id' => $plan->id,
            'price' => 0,
            'currency' => 'EUR',
            'interval' => 'month',
        ]);
    }

    /**
     * Test: Validation fails with invalid name format.
     */
    public function test_update_with_invalid_name(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $plan = SubscriptionPlan::where('code', 'free')->first();

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/admin/plans/{$plan->id}", [
                'name' => str_repeat('a', 300), // Too long
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /**
     * Test: Validation fails with invalid features format.
     */
    public function test_update_with_invalid_features(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $plan = SubscriptionPlan::where('code', 'free')->first();

        $response = $this->actingAs($superadmin)
            ->patchJson("/api/admin/plans/{$plan->id}", [
                'features' => 'not_an_array', // Should be array
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['features']);
    }

    /**
     * Test: Audit log is created when plan is updated.
     */
    public function test_audit_log_created_on_plan_update(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $plan = SubscriptionPlan::where('code', 'free')->first();

        $this->actingAs($superadmin)
            ->patchJson("/api/admin/plans/{$plan->id}", [
                'name' => 'Updated Plan Name',
            ]);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE_SUBSCRIPTION_PLAN',
            'target' => 'plan_free',
        ]);
    }

    /**
     * Test: Regular user cannot update plans.
     */
    public function test_regular_user_cannot_update_plans(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $plan = SubscriptionPlan::where('code', 'free')->first();

        $response = $this->actingAs($user)
            ->patchJson("/api/admin/plans/{$plan->id}", [
                'name' => 'Hacked',
            ]);

        $response->assertStatus(403); // Forbidden
    }

    /**
     * Test: Admin user cannot update plans (superadmin only).
     */
    public function test_admin_user_cannot_update_plans(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plan = SubscriptionPlan::where('code', 'free')->first();

        $response = $this->actingAs($admin)
            ->patchJson("/api/admin/plans/{$plan->id}", [
                'name' => 'Updated',
            ]);

        $response->assertStatus(403); // Forbidden - not superadmin
    }
}
