<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionPlanController extends Controller
{
    /**
     * List all subscription plans.
     */
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::orderBy('code')->get();

        return response()->json(
            $plans->map(fn ($plan) => [
                'id' => $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
                'price' => (float) $plan->price,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
                'features' => $plan->features ?? [],
                'limits' => $plan->limits ?? [],
                'createdAt' => $plan->created_at->toIso8601String(),
                'updatedAt' => $plan->updated_at->toIso8601String(),
            ])
        );
    }

    /**
     * Show a specific subscription plan.
     */
    public function show(SubscriptionPlan $plan): JsonResponse
    {
        return response()->json([
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'price' => (float) $plan->price,
            'currency' => $plan->currency,
            'interval' => $plan->interval,
            'features' => $plan->features ?? [],
            'limits' => $plan->limits ?? [],
            'createdAt' => $plan->created_at->toIso8601String(),
            'updatedAt' => $plan->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Update a subscription plan.
     * Only name, features, and limits can be modified.
     */
    public function update(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'features' => 'sometimes|array',
            'limits' => 'sometimes|array',
        ]);

        $changes = [];

        if ($request->has('name') && $request->input('name') !== $plan->name) {
            $changes['name'] = ['old' => $plan->name, 'new' => $request->input('name')];
            $plan->update(['name' => $request->input('name')]);
        }

        if ($request->has('features') && $request->input('features') !== $plan->features) {
            $changes['features'] = ['old' => $plan->features, 'new' => $request->input('features')];
            $plan->update(['features' => $request->input('features')]);
        }

        if ($request->has('limits') && $request->input('limits') !== $plan->limits) {
            $changes['limits'] = ['old' => $plan->limits, 'new' => $request->input('limits')];
            $plan->update(['limits' => $request->input('limits')]);
        }

        if (count($changes) > 0) {
            AuditLog::log('UPDATE_SUBSCRIPTION_PLAN', 'plan_' . $plan->code, $changes);
        }

        return response()->json([
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'price' => (float) $plan->price,
            'currency' => $plan->currency,
            'interval' => $plan->interval,
            'features' => $plan->features ?? [],
            'limits' => $plan->limits ?? [],
            'message' => 'Plan updated successfully',
            'changesApplied' => count($changes) > 0 ? count($changes) : 'No changes',
        ]);
    }
}
