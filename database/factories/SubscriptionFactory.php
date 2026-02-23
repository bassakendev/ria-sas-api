<?php

namespace Database\Factories;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null, // Must be set by caller
            'plan' => fake()->randomElement(['free', 'pro']),
            'status' => fake()->randomElement(['active', 'trialing', 'canceled']),
            'stripe_subscription_id' => 'sub_' . fake()->unique()->numerify('###############'),
            'start_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'next_billing_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'trial_ends_at' => null,
            'canceled_at' => null,
        ];
    }

    /**
     * Create a free plan subscription.
     */
    public function free(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'plan' => 'free',
                'price' => 0,
            ];
        });
    }

    /**
     * Create a pro plan subscription.
     */
    public function pro(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'plan' => 'pro',
                'price' => 12,
            ];
        });
    }

    /**
     * Create an active subscription.
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
                'trial_ends_at' => null,
                'canceled_at' => null,
            ];
        });
    }

    /**
     * Create a trialing subscription.
     */
    public function trialing(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'trialing',
                'trial_ends_at' => now()->addDays(14)->format('Y-m-d'),
                'canceled_at' => null,
            ];
        });
    }

    /**
     * Create a canceled subscription.
     */
    public function canceled(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'canceled',
                'canceled_at' => now()->format('Y-m-d'),
            ];
        });
    }
}
