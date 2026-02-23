<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscriptionRequest;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Services\StripeService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }
    /**
     * Get the current user's active subscription.
     */
    public function getCurrent(Request $request): JsonResponse
    {
        $subscription = Subscription::where('user_id', $request->user()->id)
            ->where('status', '!=', 'canceled')
            ->latest()
            ->first();

        if (!$subscription) {
            // Create default free subscription
            $subscription = Subscription::create([
                'user_id' => $request->user()->id,
                'plan' => 'free',
                'status' => 'active',
                'billing_period' => 'month',
                'start_date' => now(),
                'price' => 0,
            ]);
        }

        return response()->json([
            'userId' => $subscription->user_id,
            'plan' => $subscription->plan,
            'status' => $subscription->status,
            'billingPeriod' => $subscription->billing_period,
            'startDate' => $subscription->start_date->toIso8601String(),
            'nextBillingDate' => $subscription->next_billing_date?->toIso8601String(),
            'canceledAt' => $subscription->canceled_at?->toIso8601String(),
        ]);
    }

    /**
     * Get available subscription plans (public).
     */
    public function getPlans(): JsonResponse
    {
        $plans = [
            Subscription::getPlanDetails('free'),
            Subscription::getPlanDetails('pro'),
        ];

        return response()->json($plans);
    }

    /**
     * Upgrade to a new plan.
     */
    public function upgrade(SubscriptionRequest $request): JsonResponse
    {
        try {
            $subscription = Subscription::where('user_id', $request->user()->id)
                ->where('status', '!=', 'canceled')
                ->latest()
                ->firstOrFail();

            $newPlan = $request->getPlan() ?? $request->validated('planId');
            $billingPeriod = $request->validated('billingPeriod', 'month');

            if ($newPlan === $subscription->plan) {
                return response()->json(['message' => 'Already on this plan'], 400);
            }

            // Upgrade plan
            $subscription->upgradeToPlan($newPlan, $billingPeriod);

            // Integrate with Stripe
            $stripeSubscription = $this->stripeService->updateSubscription($request->user(), $newPlan);

            return response()->json([
                'userId' => $subscription->user_id,
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'message' => 'Subscription plan updated successfully',
                'startDate' => $subscription->start_date->toIso8601String(),
                'nextBillingDate' => $subscription->next_billing_date->toIso8601String(),
            ], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Downgrade to a new plan.
     */
    public function downgrade(SubscriptionRequest $request): JsonResponse
    {
        try {
            $subscription = Subscription::where('user_id', $request->user()->id)
                ->where('status', '!=', 'canceled')
                ->latest()
                ->firstOrFail();

            $newPlan = $request->getPlan() ?? $request->validated('planId');
            $effectiveDate = $request->validated('effectiveDate');

            if ($newPlan === $subscription->plan) {
                return response()->json(['message' => 'Already on this plan'], 400);
            }

            // Downgrade plan
            $subscription->downgradeToPlan($newPlan, $effectiveDate);

            // Integrate with Stripe
            $stripeSubscription = $this->stripeService->updateSubscription($request->user(), $newPlan);

            return response()->json([
                'userId' => $subscription->user_id,
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'message' => 'Subscription plan updated successfully',
                'startDate' => $subscription->start_date->toIso8601String(),
                'downgradeEffectiveDate' => $subscription->next_billing_date->toIso8601String(),
            ], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(SubscriptionRequest $request): JsonResponse
    {
        try {
            $subscription = Subscription::where('user_id', $request->user()->id)
                ->where('status', '!=', 'canceled')
                ->latest()
                ->firstOrFail();

            // Log cancellation reason & feedback
            $reason = $request->validated('reason');
            $feedback = $request->validated('feedback');

            // TODO: Store cancellation reason and feedback in a dedicated table

            // Cancel subscription
            $subscription->cancel();

            // Integrate with Stripe
            $this->stripeService->cancelSubscription($request->user(), immediately: false);

            return response()->json([
                'message' => 'Souscription annulée avec succès',
                'canceledAt' => $subscription->canceled_at->toIso8601String(),
                'credits' => 0, // TODO: Calculate unused credits
            ], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Reactivate a canceled subscription.
     */
    public function reactivate(Request $request): JsonResponse
    {
        try {
            $subscription = Subscription::where('user_id', $request->user()->id)
                ->where('status', 'canceled')
                ->latest()
                ->firstOrFail();

            // Check if within 30 days
            if ($subscription->canceled_at && now()->diffInDays($subscription->canceled_at) > 30) {
                return response()->json(['message' => 'Cannot reactivate after 30 days'], 409);
            }

            // Reactivate subscription
            $subscription->reactivate();

            // Integrate with Stripe
            $this->stripeService->reactivateSubscription($request->user());

            return response()->json([
                'userId' => $subscription->user_id,
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'reactivatedAt' => now()->toIso8601String(),
            ], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Get subscription billing invoices.
     */
    public function getInvoices(Request $request): JsonResponse
    {
        $subscription = Subscription::where('user_id', $request->user()->id)
            ->where('status', '!=', 'canceled')
            ->latest()
            ->firstOrFail();

        $page = $request->query('page', 1);
        $limit = $request->query('limit', 10);
        $status = $request->query('status');

        $query = $subscription->invoices();

        if ($status) {
            $query->where('status', $status);
        }

        $invoices = $query->orderBy('invoice_date', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'total' => $invoices->total(),
            'page' => $page,
            'limit' => $limit,
            'invoices' => $invoices->map(fn (SubscriptionInvoice $invoice) => [
                'id' => $invoice->id,
                'subscriptionId' => $invoice->subscription_id,
                'amount' => $invoice->amount,
                'currency' => $invoice->currency,
                'status' => $invoice->status,
                'invoiceDate' => $invoice->invoice_date->toIso8601String(),
                'dueDate' => $invoice->due_date->toIso8601String(),
                'paidDate' => $invoice->paid_date?->toIso8601String(),
                'pdfUrl' => $invoice->pdf_url,
            ]),
        ]);
    }

    /**
     * Get subscription usage statistics.
     */
    public function getUsage(Request $request): JsonResponse
    {
        $subscription = Subscription::where('user_id', $request->user()->id)
            ->where('status', '!=', 'canceled')
            ->latest()
            ->firstOrFail();

        $user = $request->user();

        // Get current month invoices
        $invoicesThisMonth = $user->invoices()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        // Get total clients
        $clientsCreated = $user->clients()->count();

        // Get plan limits
        $planDetails = Subscription::getPlanDetails($subscription->plan);
        $limits = $planDetails['limits'];

        // Calculate storage (simplified)
        $storageUsed = '0 MB'; // TODO: Calculate actual storage usage
        $storageLimit = $limits['storage'];

        // Calculate percentage
        $percentageUsed = 0;
        if ($limits['invoicesPerMonth'] > 0) {
            $percentageUsed = round(($invoicesThisMonth / $limits['invoicesPerMonth']) * 100);
        }

        return response()->json([
            'invoicesThisMonth' => $invoicesThisMonth,
            'invoicesLimit' => $limits['invoicesPerMonth'],
            'clientsCreated' => $clientsCreated,
            'clientsLimit' => $limits['clients'],
            'storageUsed' => $storageUsed,
            'storageLimit' => $storageLimit,
            'percentageUsed' => $percentageUsed,
        ]);
    }
}
