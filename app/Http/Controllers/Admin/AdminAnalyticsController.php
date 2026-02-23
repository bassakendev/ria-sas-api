<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnalyticsController extends Controller
{
    /**
     * Get analytics statistics for a given period.
     */
    public function stats(Request $request): JsonResponse
    {
        $period = $request->query('period', 'week');

        $days = match($period) {
            'week' => 7,
            'month' => 30,
            'year' => 365,
            default => 7,
        };

        $startDate = now()->subDays($days);

        // Generate users growth data
        $users = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = User::where('created_at', '<=', $date)->count();
            $users[] = [
                'date' => $date->toDateString(),
                'value' => $count,
            ];
        }

        // Generate revenue data (simplified: count paid invoices)
        $revenue = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dailyRevenue = Invoice::where('status', 'paid')
                ->whereDate('paid_date', $date)
                ->sum('total');
            $revenue[] = [
                'date' => $date->toDateString(),
                'value' => round($dailyRevenue, 2),
            ];
        }

        // Calculate churn rate for each day
        $churnRate = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);

            // Users who canceled on this date
            $canceled = Subscription::whereDate('canceled_at', $date)->count();

            // Total active subscriptions at start of day
            $totalActive = Subscription::where('status', 'active')
                ->where('start_date', '<=', $date)
                ->count();

            $rate = $totalActive > 0 ? round(($canceled / $totalActive) * 100, 1) : 0;

            $churnRate[] = [
                'date' => $date->toDateString(),
                'value' => $rate,
            ];
        }

        return response()->json([
            'users' => $users,
            'revenue' => $revenue,
            'churnRate' => $churnRate,
            'period' => $period,
        ]);
    }
}
