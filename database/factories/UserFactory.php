<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company_name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'subscription_plan' => 'free',
            'subscription_status' => 'active',
            'subscription_id' => null,
            'role' => 'user',
            'status' => 'active',
        ];
    }

    /**
     * Indicate user is Pro plan subscriber.
     */
    public function proPlan(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'subscription_plan' => 'pro',
                'subscription_status' => 'active',
                'subscription_id' => 'sub_' . fake()->unique()->numberBetween(1000000, 9999999),
            ];
        });
    }

    /**
     * Indicate user is trialing Pro plan.
     */
    public function trialPlan(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'subscription_plan' => 'pro',
                'subscription_status' => 'trialing',
                'subscription_id' => 'sub_trial_' . fake()->unique()->numberBetween(1000000, 9999999),
            ];
        });
    }

    /**
     * Indicate user is superadmin.
     */
    public function superadmin(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'email' => 'superadmin@ria-sas.local',
                'name' => 'SuperAdmin',
                'company_name' => 'RIA-SAS',
                'role' => 'superadmin',
            ];
        });
    }

    /**
     * Indicate user is admin.
     */
    public function admin(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'role' => 'admin',
            ];
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this;
    }
}

