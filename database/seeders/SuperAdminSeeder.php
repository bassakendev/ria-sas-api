<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()
            ->superadmin()
            ->create([
                'email' => 'superadmin@ria-sas.local',
                'password' => \Illuminate\Support\Facades\Hash::make('superadmin123'),
            ]);
    }
}
