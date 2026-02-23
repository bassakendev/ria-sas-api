<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubscriptionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::whereNotIn('role', ['superadmin', 'admin'])->get();

        foreach ($users as $user) {
            // Create subscription record for each user
            Subscription::create([
                'user_id' => $user->id,
                'plan' => $user->subscription_plan ?? 'free',
                'status' => $user->subscription_status,
                'stripe_subscription_id' => $user->subscription_id,
                'start_date' => fake()->dateTimeBetween('-180 days', 'now'),
                'next_billing_date' => $user->subscription_status !== 'canceled'
                    ? fake()->dateTimeBetween('+1 days', '+60 days')
                    : null,
                'canceled_at' => $user->subscription_status === 'canceled'
                    ? fake()->dateTimeBetween('-30 days', 'now')
                    : null,
                'trial_ends_at' => $user->subscription_status === 'trialing'
                    ? fake()->dateTimeBetween('+1 days', '+14 days')
                    : null,
            ]);
        }
    }
}


