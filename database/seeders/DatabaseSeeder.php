<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run seeders in proper order
        $this->call([
            SubscriptionPlanSeeder::class, // Plans must be first
            SuperAdminSeeder::class,
            UsersWithPlansSeeder::class,
            ClientsSeeder::class,
            InvoicesSeeder::class,
            SubscriptionsSeeder::class,
            FeedbackSeeder::class,
        ]);
    }
}
