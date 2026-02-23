<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed subscription plans for tests
        $seeder = new \Database\Seeders\SubscriptionPlanSeeder();
        $seeder->run();
    }

    /**
     * Test get current subscription.
     */
    public function test_user_can_get_subscription(): void
    {
        $user = User::factory()->proPlan()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'status' => 'active',
            'stripe_subscription_id' => 'sub_123',
            'start_date' => now(),
            'next_billing_date' => now()->addMonth(),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson('/api/subscription', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('plan', 'pro')
            ->assertJsonPath('status', 'active');
    }

    /**
     * Test get available plans (public).
     */
    public function test_anyone_can_get_plans(): void
    {
        $response = $this->getJson('/api/subscription/plans');

        $response->assertStatus(200)
            ->assertJsonCount(2); // free and pro
    }

    /**
     * Test upgrade to pro plan.
     */
    public function test_user_can_upgrade_to_pro(): void
    {
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'free',
            'status' => 'active',
            'start_date' => now(),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->postJson('/api/subscription/upgrade', [
            'planId' => 'pro',
            'billingPeriod' => 'month',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('plan', 'pro');
    }

    /**
     * Test downgrade to free plan.
     */
    public function test_user_can_downgrade_to_free(): void
    {
        $user = User::factory()->proPlan()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'status' => 'active',
            'stripe_subscription_id' => 'sub_123',
            'start_date' => now(),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->postJson('/api/subscription/downgrade', [
            'planId' => 'free',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('plan', 'free');
    }

    /**
     * Test cancel subscription.
     */
    public function test_user_can_cancel_subscription(): void
    {
        $user = User::factory()->proPlan()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'status' => 'active',
            'stripe_subscription_id' => 'sub_123',
            'start_date' => now(),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->postJson('/api/subscription/cancel', [
            'reason' => 'Too expensive',
            'feedback' => 'Great product but not for our use case',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test reactivate subscription within 30 days.
     */
    public function test_user_can_reactivate_subscription(): void
    {
        $user = User::factory()->proPlan()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'status' => 'canceled',
            'stripe_subscription_id' => 'sub_123',
            'start_date' => now()->subMonth(),
            'canceled_at' => now()->subDays(5),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->postJson('/api/subscription/reactivate', [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'active');
    }

    /**
     * Test get subscription invoices.
     */
    public function test_user_can_get_subscription_invoices(): void
    {
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'free',
            'status' => 'active',
            'start_date' => now(),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson('/api/subscription/invoices', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total',
                'page',
                'limit',
                'invoices',
            ]);
    }

    /**
     * Test get usage statistics.
     */
    public function test_user_can_get_usage_statistics(): void
    {
        $user = User::factory()->proPlan()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'status' => 'active',
            'stripe_subscription_id' => 'sub_123',
            'start_date' => now(),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson('/api/subscription/usage', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'invoicesThisMonth',
                'invoicesLimit',
                'clientsCreated',
                'clientsLimit',
                'storageUsed',
                'storageLimit',
                'percentageUsed',
            ]);
    }

    /**
     * Test cannot upgrade to same plan.
     */
    public function test_user_cannot_upgrade_to_same_plan(): void
    {
        $user = User::factory()->proPlan()->create();
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'status' => 'active',
            'stripe_subscription_id' => 'sub_123',
            'start_date' => now(),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->postJson('/api/subscription/upgrade', [
            'planId' => 'pro',
            'billingPeriod' => 'month',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(400);
    }
}
