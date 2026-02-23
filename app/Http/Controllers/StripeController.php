<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Create a Stripe checkout session for subscription upgrade.
     *
     * @bodyParam plan string required The plan to upgrade to ('free' or 'pro'). Example: pro
     */
    public function createCheckoutSession(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'plan' => 'required|in:free,pro',
            ]);

            $user = $request->user();
            $plan = $request->input('plan');

            // Create checkout session
            $checkoutUrl = $this->stripeService->createCheckoutSession($user, $plan);

            return response()->json([
                'success' => true,
                'checkout_url' => $checkoutUrl,
                'message' => 'Checkout session created successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create checkout session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a Stripe billing portal session.
     * Allows customers to manage their subscriptions.
     */
    public function createPortalSession(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Create portal session
            $portalUrl = $this->stripeService->createPortalSession($user);

            return response()->json([
                'success' => true,
                'portal_url' => $portalUrl,
                'message' => 'Billing portal session created successfully',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create portal session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a subscription.
     *
     * @bodyParam immediately boolean Cancel immediately or at period end. Example: false
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'immediately' => 'sometimes|boolean',
            ]);

            $user = $request->user();
            $immediately = $request->boolean('immediately', false);

            // Cancel subscription
            $subscription = $this->stripeService->cancelSubscription($user, $immediately);

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => $immediately ? 'Subscription canceled immediately' : 'Subscription will be canceled at period end',
                'subscription_status' => $subscription->status,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle Stripe webhook.
     * This endpoint should be public and not require authentication.
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('Stripe-Signature');

            if (!$signature) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing Stripe-Signature header',
                ], 400);
            }

            // Process webhook
            $result = $this->stripeService->handleWebhook($payload, $signature);

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current subscription details.
     */
    public function getSubscriptionDetails(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $subscription = $this->stripeService->getSubscriptionDetails($user);

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No subscription found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end,
                    'cancel_at_period_end' => $subscription->cancel_at_period_end,
                    'canceled_at' => $subscription->canceled_at,
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer from Stripe.
     */
    public function getCustomer(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $customer = $this->stripeService->getCustomer($user);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'No customer found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'description' => $customer->description,
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve customer: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List invoices for current user.
     *
     * @queryParam limit int The number of invoices to retrieve. Example: 10
     */
    public function listInvoices(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'sometimes|integer|min:1|max:100',
            ]);

            $user = $request->user();
            $limit = $request->input('limit', 10);

            $invoices = $this->stripeService->listInvoices($user, $limit);

            return response()->json([
                'success' => true,
                'invoices' => array_map(fn ($invoices) => [
                    'id' => $invoices->id,
                    'number' => $invoices->number,
                    'amount' => $invoices->amount_paid / 100,
                    'currency' => strtoupper($invoices->currency),
                    'status' => $invoices->status,
                    'created' => $invoices->created,
                    'paid' => $invoices->paid,
                ], $invoices),
                'count' => count($invoices),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoices: ' . $e->getMessage(),
            ], 500);
        }
    }
}

