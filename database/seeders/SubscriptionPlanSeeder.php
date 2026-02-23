<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Delete existing plans
        SubscriptionPlan::truncate();

        // Create Free Plan
        SubscriptionPlan::create([
            'code' => 'free',
            'name' => 'Plan Gratuit',
            'price' => 0,
            'currency' => 'EUR',
            'interval' => 'month',
            'features' => [
                'Jusqu\'à 5 factures par mois',
                'Gestion de 3 clients maximum',
                'Stockage de 100 MB',
                'Support par email',
            ],
            'limits' => [
                'invoicesPerMonth' => 5,
                'clients' => 3,
                'storage' => '100 MB',
                'support' => 'Email (48h)',
            ],
        ]);

        // Create Pro Plan
        SubscriptionPlan::create([
            'code' => 'pro',
            'name' => 'Plan Pro',
            'price' => 12,
            'currency' => 'EUR',
            'interval' => 'month',
            'features' => [
                'Factures illimitées',
                'Clients illimités',
                'Stockage de 10 GB',
                'Support prioritaire',
                'Export CSV avancé',
                'Personnalisation des factures',
                'Filigrane personnalisé',
                'Rapports et statistiques avancés',
            ],
            'limits' => [
                'invoicesPerMonth' => -1,
                'clients' => -1,
                'storage' => '10 GB',
                'support' => 'Email & Chat (2h)',
            ],
        ]);
    }
}

