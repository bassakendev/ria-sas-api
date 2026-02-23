<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminSubscriptionController extends Controller
{
    /**
     * List all subscriptions with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::with('user');

        // Filter by plan
        if ($plan = $request->query('plan')) {
            $query->where('plan', $plan);
        }

        // Filter by status
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $page = $request->query('page', 1);
        $limit = $request->query('limit', 10);

        $subscriptions = $query->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'total' => $subscriptions->total(),
            'page' => $page,
            'limit' => $limit,
            'subscriptions' => $subscriptions->map(fn ($sub) => [
                'id' => 'sub_' . str_pad($sub->id, 3, '0', STR_PAD_LEFT),
                'userId' => 'usr_' . str_pad($sub->user_id, 3, '0', STR_PAD_LEFT),
                'plan' => $sub->plan,
                'status' => $sub->status,
                'startDate' => $sub->start_date?->toIso8601String(),
                'nextBillingDate' => $sub->next_billing_date?->toIso8601String(),
                'canceledAt' => $sub->canceled_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Change the plan of an existing subscription.
     */
    public function changePlan(Request $request, Subscription $subscription): JsonResponse
    {
        $request->validate([
            'plan' => ['required', Rule::in(['free', 'pro'])],
        ]);

        $oldPlan = $subscription->plan;
        $newPlan = $request->input('plan');

        $subscription->update(['plan' => $newPlan]);

        // Also update user's subscription_plan
        $subscription->user->update(['subscription_plan' => $newPlan]);

        AuditLog::log('CHANGE_SUBSCRIPTION_PLAN', 'sub_' . $subscription->id, [
            'old_plan' => $oldPlan,
            'new_plan' => $newPlan,
            'user_id' => $subscription->user_id,
        ]);

        return response()->json([
            'id' => 'sub_' . str_pad($subscription->id, 3, '0', STR_PAD_LEFT),
            'userId' => 'usr_' . str_pad($subscription->user_id, 3, '0', STR_PAD_LEFT),
            'plan' => $subscription->plan,
            'status' => $subscription->status,
            'message' => 'Subscription plan updated successfully',
        ]);
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $reason = $request->input('reason');

        $subscription->cancel();

        AuditLog::log('CANCEL_SUBSCRIPTION', 'sub_' . $subscription->id, [
            'reason' => $reason,
            'user_id' => $subscription->user_id,
        ]);

        return response()->json([
            'id' => 'sub_' . str_pad($subscription->id, 3, '0', STR_PAD_LEFT),
            'status' => $subscription->status,
            'message' => 'Subscription canceled successfully',
        ]);
    }
}
