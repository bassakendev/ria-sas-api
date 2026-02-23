<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
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
     * Test superadmin can get weekly stats.
     */
    public function test_superadmin_can_get_weekly_stats(): void
    {
        // Create some test data
        User::factory()->count(5)->create();

        $response = $this->getJson('/api/admin/stats?period=week', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period',
                'users' => [
                    '*' => ['date', 'value'],
                ],
                'revenue' => [
                    '*' => ['date', 'value'],
                ],
                'churnRate' => [
                    '*' => ['date', 'value'],
                ],
            ]);

        $this->assertEquals('week', $response->json('period'));
        $this->assertCount(7, $response->json('users'));
        $this->assertCount(7, $response->json('revenue'));
        $this->assertCount(7, $response->json('churnRate'));
    }

    /**
     * Test superadmin can get monthly stats.
     */
    public function test_superadmin_can_get_monthly_stats(): void
    {
        $response = $this->getJson('/api/admin/stats?period=month', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $this->assertEquals('month', $response->json('period'));
        $this->assertCount(30, $response->json('users'));
    }

    /**
     * Test superadmin can get yearly stats.
     */
    public function test_superadmin_can_get_yearly_stats(): void
    {
        $response = $this->getJson('/api/admin/stats?period=year', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $this->assertEquals('year', $response->json('period'));
        $this->assertCount(365, $response->json('users'));
    }

    /**
     * Test default period is week.
     */
    public function test_default_period_is_week(): void
    {
        $response = $this->getJson('/api/admin/stats', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $this->assertEquals('week', $response->json('period'));
    }

    /**
     * Test invalid period defaults to week.
     */
    public function test_invalid_period_defaults_to_week(): void
    {
        $response = $this->getJson('/api/admin/stats?period=invalid', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        // Controller returns invalid value as-is but uses default 7 days
        $this->assertEquals('invalid', $response->json('period'));
        $this->assertCount(7, $response->json('users'));
    }

    /**
     * Test users data is cumulative.
     */
    public function test_users_data_is_cumulative(): void
    {
        // Create users at specific times
        User::factory()->create(['created_at' => now()->subDays(5)]);
        User::factory()->create(['created_at' => now()->subDays(3)]);
        User::factory()->create(['created_at' => now()->subDays(1)]);

        $response = $this->getJson('/api/admin/stats?period=week', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);

        $users = $response->json('users');
        $values = array_column($users, 'value');

        // Values should be non-decreasing (cumulative)
        for ($i = 1; $i < count($values); $i++) {
            $this->assertGreaterThanOrEqual($values[$i - 1], $values[$i]);
        }
    }

    /**
     * Test revenue calculation includes paid invoices.
     */
    public function test_revenue_calculation_includes_paid_invoices(): void
    {
        $user = User::factory()->create();

        // Create a paid invoice from 2 days ago
        Invoice::factory()->paid()->create([
            'user_id' => $user->id,
            'total' => 1200, // €12.00
            'paid_date' => now()->subDays(2)->format('Y-m-d'),
        ]);

        $response = $this->getJson('/api/admin/stats?period=week', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);

        $revenue = $response->json('revenue');
        $totalRevenue = array_sum(array_column($revenue, 'value'));

        $this->assertGreaterThan(0, $totalRevenue);
    }

    /**
     * Test churn rate is percentage.
     */
    public function test_churn_rate_is_percentage(): void
    {
        // Create some subscriptions
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Subscription::factory()->active()->create(['user_id' => $user1->id]);
        Subscription::factory()->canceled()->create([
            'user_id' => $user2->id,
            'updated_at' => now()->subDays(2),
        ]);

        $response = $this->getJson('/api/admin/stats?period=week', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);

        $churnRate = $response->json('churnRate');

        // All values should be between 0 and 100
        foreach ($churnRate as $item) {
            $this->assertGreaterThanOrEqual(0, $item['value']);
            $this->assertLessThanOrEqual(100, $item['value']);
        }
    }

    /**
     * Test regular user cannot access analytics.
     */
    public function test_regular_user_cannot_access_analytics(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson('/api/admin/stats', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }
}
