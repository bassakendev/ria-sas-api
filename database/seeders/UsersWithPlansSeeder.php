<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UsersWithPlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 15 free plan users
        User::factory()
            ->count(15)
            ->create();

        // Create 8 pro plan users
        User::factory()
            ->count(8)
            ->proPlan()
            ->create();

        // Create 3 trial pro users
        User::factory()
            ->count(3)
            ->trialPlan()
            ->create();

        // Create 2 admin users
        User::factory()
            ->count(2)
            ->admin()
            ->create();
    }
}
