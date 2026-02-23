<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * List all users with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Search by email
        if ($search = $request->query('query')) {
            $query->where('email', 'like', "%{$search}%");
        }

        // Filter by role
        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        // Filter by status
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $page = $request->query('page', 1);
        $limit = $request->query('limit', 10);

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'total' => $users->total(),
            'page' => $page,
            'limit' => $limit,
            'users' => $users->map(fn ($user) => [
                'id' => 'usr_' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status ?? 'active',
                'plan' => $user->subscription_plan,
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
                'createdAt' => $user->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Get detailed user information.
     */
    public function show(User $user): JsonResponse
    {
        $subscription = Subscription::where('user_id', $user->id)
            ->where('status', '!=', 'canceled')
            ->latest()
            ->first();

        return response()->json([
            'id' => 'usr_' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status ?? 'active',
            'plan' => $user->subscription_plan,
            'planName' => $user->subscription_plan === 'pro' ? 'Professional' : 'Free',
            'lastLoginAt' => $user->last_login_at?->toIso8601String(),
            'createdAt' => $user->created_at->toIso8601String(),
            'subscriptionId' => $subscription ? 'sub_' . str_pad($subscription->id, 3, '0', STR_PAD_LEFT) : null,
        ]);
    }

    /**
     * Change user role.
     */
    public function changeRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role' => ['required', Rule::in(['user', 'admin', 'superadmin'])],
        ]);

        $oldRole = $user->role;
        $newRole = $request->input('role');

        $user->update(['role' => $newRole]);

        AuditLog::log('CHANGE_ROLE', 'usr_' . str_pad($user->id, 3, '0', STR_PAD_LEFT), [
            'old_role' => $oldRole,
            'new_role' => $newRole,
        ]);

        return response()->json([
            'id' => 'usr_' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
            'email' => $user->email,
            'role' => $user->role,
            'message' => 'User role updated successfully',
        ]);
    }

    /**
     * Suspend a user account.
     */
    public function suspend(User $user): JsonResponse
    {
        $user->update(['status' => 'suspended']);

        AuditLog::log('SUSPEND_USER', 'usr_' . $user->id);

        return response()->json([
            'id' => 'usr_' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
            'status' => $user->status,
            'message' => 'User suspended successfully',
        ]);
    }

    /**
     * Activate a suspended user account.
     */
    public function activate(User $user): JsonResponse
    {
        $user->update(['status' => 'active']);

        AuditLog::log('ACTIVATE_USER', 'usr_' . $user->id);

        return response()->json([
            'id' => 'usr_' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
            'status' => $user->status,
            'message' => 'User activated successfully',
        ]);
    }

    /**
     * Delete a user permanently.
     */
    public function destroy(User $user): JsonResponse
    {
        AuditLog::log('DELETE_USER', 'usr_' . $user->id, [
            'email' => $user->email,
        ]);

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Assign a plan directly to a user (without payment).
     */
    public function assignPlan(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'plan' => ['required', Rule::in(['free', 'pro'])],
            'reason' => 'nullable|string|max:500',
        ]);

        $plan = $request->input('plan');
        $reason = $request->input('reason');

        // Update user subscription plan
        $user->update([
            'subscription_plan' => $plan,
            'subscription_status' => 'active',
        ]);

        // Create or update subscription
        $subscription = Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'plan' => $plan,
                'status' => 'active',
                'start_date' => now(),
                'next_billing_date' => now()->addMonth(),
            ]
        );

        AuditLog::log('ASSIGN_PLAN', 'usr_' . $user->id, [
            'plan' => $plan,
            'reason' => $reason,
        ]);

        return response()->json([
            'id' => 'usr_' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
            'subscription_plan' => $user->subscription_plan,
            'message' => 'Plan assigned successfully',
        ]);
    }
}
