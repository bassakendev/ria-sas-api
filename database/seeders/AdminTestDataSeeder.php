<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminTestDataSeeder extends Seeder
{
    /**
     * Seed admin test data.
     */
    public function run(): void
    {
        // Update some users to have suspended status
        $usersToSuspend = User::where('role', 'user')
            ->where('subscription_plan', 'free')
            ->inRandomOrder()
            ->limit(3)
            ->get();

        foreach ($usersToSuspend as $user) {
            $user->update(['status' => 'suspended']);
        }

        // Update last_login_at for some users
        User::where('role', '!=', 'superadmin')
            ->chunk(10, function ($users) {
                foreach ($users as $user) {
                    $user->update([
                        'last_login_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
                    ]);
                }
            });

        // Add admin responses to some feedbacks
        $feedbacksToRespond = Feedback::whereIn('status', ['new', 'read'])
            ->inRandomOrder()
            ->limit(15)
            ->get();

        $adminEmail = 'superadmin@ria-sas.local';

        $responses = [
            'Thank you for your feedback. We are working on this issue.',
            'We appreciate your suggestion and will consider it for future updates.',
            'This bug has been fixed in the latest release. Please update your app.',
            'We understand your concern. Our team is investigating this matter.',
            'Feature request noted. We will add it to our roadmap.',
            'Thank you for reporting this. A fix will be deployed shortly.',
        ];

        foreach ($feedbacksToRespond as $feedback) {
            $feedback->update([
                'response' => $responses[array_rand($responses)],
                'responded_at' => now()->subDays(rand(0, 7)),
                'responded_by' => $adminEmail,
                'status' => 'read',
            ]);
        }

        // Create some audit log entries
        $superadmin = User::where('email', 'superadmin@ria-sas.local')->first();

        if ($superadmin) {
            $actions = [
                ['action' => 'SUSPEND_USER', 'target' => 'usr_005'],
                ['action' => 'ACTIVATE_USER', 'target' => 'usr_012'],
                ['action' => 'CHANGE_ROLE', 'target' => 'usr_020', 'metadata' => ['old_role' => 'user', 'new_role' => 'admin']],
                ['action' => 'ASSIGN_PLAN', 'target' => 'usr_008', 'metadata' => ['plan' => 'pro', 'reason' => 'Promotion']],
                ['action' => 'CANCEL_SUBSCRIPTION', 'target' => 'sub_015', 'metadata' => ['reason' => 'Customer request']],
                ['action' => 'UPDATE_FEEDBACK_RESPONSE', 'target' => 'fb_003'],
                ['action' => 'DELETE_USER', 'target' => 'usr_099', 'metadata' => ['email' => 'deleted@example.com']],
                ['action' => 'UPDATE_SETTINGS', 'target' => null, 'metadata' => ['section' => 'security']],
            ];

            foreach ($actions as $actionData) {
                AuditLog::create([
                    'actor_id' => $superadmin->id,
                    'actor_email' => $superadmin->email,
                    'action' => $actionData['action'],
                    'target' => $actionData['target'] ?? null,
                    'ip_address' => '192.168.1.' . rand(1, 254),
                    'metadata' => $actionData['metadata'] ?? null,
                    'created_at' => now()->subDays(rand(0, 14))->subHours(rand(0, 23)),
                ]);
            }
        }

        $this->command->info('Admin test data seeded successfully.');
    }
}
