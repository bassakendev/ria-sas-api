<?php

namespace Database\Seeders;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Seeder;

class FeedbackSeeder extends Seeder
{
    private array $feedbackExamples = [
        'question' => [
            ['subject' => 'Comment exporter les factures en PDF?', 'message' => 'Bonjour, je cherche à exporter mes factures en PDF pour les archiver. Comment faire?'],
            ['subject' => 'Possibilité d\'importer des clients en masse?', 'message' => 'Est-ce qu\'il y a une fonctionnalité pour importer une liste de clients via CSV?'],
            ['subject' => 'API disponible?', 'message' => 'Avez-vous une API REST que je peux utiliser pour intégrer le système avec ma comptabilité?'],
            ['subject' => 'Support du multi-devise?', 'message' => 'Supporte le système plusieurs devises? Je travaille avec des clients internationaux.'],
        ],
        'bug' => [
            ['subject' => 'Calcul de taxe incorrect', 'message' => 'La taxe n\'est pas calculée correctement sur certaines factures. Elle semble être doublée.'],
            ['subject' => 'Erreur lors du téléchargement PDF', 'message' => 'Quand j\'essaie de télécharger une facture en PDF, j\'obtiens une erreur 500.'],
            ['subject' => 'Les emails ne sont pas envoyés', 'message' => 'Impossible d\'envoyer des factures par email. Le bouton ne fonctionne pas.'],
            ['subject' => 'Pagination ne fonctionne pas correctement', 'message' => 'La pagination sur la liste des factures saute des éléments.'],
        ],
        'feature' => [
            ['subject' => 'Ajouter la facturation récurrente', 'message' => 'Pourriez-vous ajouter la possibilité de créer des factures récurrentes mensuelles?'],
            ['subject' => 'Rappels de paiement automatiques', 'message' => 'Ce serait sympa d\'avoir des emails de rappel automatiques 3 jours avant l\'échéance.'],
            ['subject' => 'Intégration Stripe complète', 'message' => 'Pouvez-vous ajouter une meilleure intégration avec Stripe pour les paiements directs?'],
            ['subject' => 'Modèles de factures personnalisables', 'message' => 'J\'aimerais pouvoir créer des modèles de factures personnalisés avec mon logo.'],
            ['subject' => 'Rapport d\'analyse de chiffre d\'affaires', 'message' => 'Un tableau de bord avec des graphiques de tendances serait très utile.'],
        ],
        'other' => [
            ['subject' => 'Commentaire général', 'message' => 'J\'aime bien votre application, c\'est simple et efficace. Continuez comme ça!'],
            ['subject' => 'Performance', 'message' => 'L\'application a commencé à ralentir récemment avec beaucoup de données.'],
            ['subject' => 'Interface utilisateur', 'message' => 'Pensez-vous à refaire l\'interface? Elle est un peu datée.'],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::whereNotIn('role', ['superadmin', 'admin'])->get();

        foreach ($users as $user) {
            // Create 0-5 feedback per user
            $feedbackCount = rand(0, 5);

            for ($i = 0; $i < $feedbackCount; $i++) {
                $type = array_rand($this->feedbackExamples);
                $example = $this->feedbackExamples[$type][array_rand($this->feedbackExamples[$type])];

                $status = fake()->randomElement(['new', 'new', 'read', 'closed']);

                Feedback::create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'email' => $user->email,
                    'subject' => $example['subject'],
                    'message' => $example['message'],
                    'status' => $status,
                    'created_at' => fake()->dateTimeBetween('-60 days', 'now'),
                ]);
            }
        }
    }
}
