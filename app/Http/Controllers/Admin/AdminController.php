<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Get admin dashboard overview with KPIs.
     */
    public function overview(Request $request): JsonResponse
    {
        // Calculate metrics
        $usersTotal = User::count();
        $usersActive = User::where('status', 'active')->count();

        // Calculate MRR (Monthly Recurring Revenue)
        $mrr = Subscription::where('status', 'active')
            ->where('plan', 'pro')
            ->count() * 12; // €12 per pro user

        // Calculate churn rate (simplified)
        $totalLastMonth = Subscription::whereMonth('created_at', now()->subMonth())->count();
        $canceledThisMonth = Subscription::where('status', 'canceled')
            ->whereMonth('canceled_at', now())
            ->count();
        $churnRate = $totalLastMonth > 0 ? round(($canceledThisMonth / $totalLastMonth) * 100, 1) : 0;

        // Pending feedbacks and support tickets
        $pendingFeedbacks = Feedback::where('status', 'new')->count();
        $openTickets = Feedback::whereIn('status', ['new', 'read'])->count();

        // Subscriptions breakdown
        $subscriptions = [
            'free' => Subscription::where('plan', 'free')->where('status', 'active')->count(),
            'pro' => Subscription::where('plan', 'pro')->where('status', 'active')->count(),
            'trial' => Subscription::where('status', 'trialing')->count(),
        ];

        // Support metrics
        $avgResponseTimeHours = 2.5; // TODO: Calculate from actual feedback response times
        $slaBreaches = 1; // TODO: Calculate based on SLA rules

        // System health
        $system = [
            'status' => 'healthy',
            'lastBackupAt' => now()->subHours(6)->toIso8601String(),
            'queueDepth' => 0,
        ];

        // Recent activity (last 10)
        $recentActivity = AuditLog::with('actor')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($log) => [
                'id' => 'act_' . $log->id,
                'type' => $this->getActivityType($log->action),
                'title' => $this->getActivityTitle($log->action),
                'description' => $log->actor_email,
                'createdAt' => $log->created_at->toIso8601String(),
            ]);

        // System alerts
        $alerts = [];
        if ($pendingFeedbacks > 10) {
            $alerts[] = [
                'id' => 'alr_feedback',
                'severity' => 'warning',
                'title' => 'High pending feedback count',
                'description' => "$pendingFeedbacks feedbacks awaiting review",
                'createdAt' => now()->toIso8601String(),
            ];
        }

        return response()->json([
            'metrics' => [
                'usersTotal' => $usersTotal,
                'usersActive' => $usersActive,
                'mrr' => $mrr,
                'churnRate' => $churnRate,
                'pendingFeedbacks' => $pendingFeedbacks,
                'openTickets' => $openTickets,
            ],
            'subscriptions' => $subscriptions,
            'support' => [
                'avgResponseTimeHours' => $avgResponseTimeHours,
                'slaBreaches' => $slaBreaches,
            ],
            'system' => $system,
            'recentActivity' => $recentActivity,
            'alerts' => $alerts,
        ]);
    }

    /**
     * Map action to activity type.
     */
    private function getActivityType(string $action): string
    {
        return match (true) {
            str_contains($action, 'USER') => 'user',
            str_contains($action, 'SUBSCRIPTION') => 'subscription',
            str_contains($action, 'FEEDBACK') => 'feedback',
            default => 'system',
        };
    }

    /**
     * Map action to human-readable title.
     */
    private function getActivityTitle(string $action): string
    {
        return match ($action) {
            'SUSPEND_USER' => 'User suspended',
            'ACTIVATE_USER' => 'User activated',
            'DELETE_USER' => 'User deleted',
            'CHANGE_ROLE' => 'User role changed',
            'ASSIGN_PLAN' => 'Plan assigned',
            'CANCEL_SUBSCRIPTION' => 'Subscription canceled',
            'UPDATE_FEEDBACK' => 'Feedback updated',
            default => ucwords(str_replace('_', ' ', strtolower($action))),
        };
    }
}
