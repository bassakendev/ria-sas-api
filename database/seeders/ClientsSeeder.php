<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClientsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            // Skip superadmin
            if ($user->role === 'superadmin') {
                continue;
            }

            // Create 2-8 clients per user depending on plan
            $clientCount = $user->subscription_plan === 'pro' ? rand(5, 10) : rand(2, 5);

            for ($i = 0; $i < $clientCount; $i++) {
                Client::create([
                    'user_id' => $user->id,
                    'name' => fake()->company(),
                    'email' => fake()->companyEmail(),
                    'phone' => fake()->phoneNumber(),
                    'address' => fake()->streetAddress(),
                    'city' => fake()->city(),
                    'postal_code' => fake()->postcode(),
                    'country' => fake()->country(),
                ]);
            }
        }
    }
}
