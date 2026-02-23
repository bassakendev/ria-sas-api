<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Exception;
use Stripe\Customer;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook;

class StripeService
{
    protected StripeClient $stripe;
    protected string $webhookSecret;

    public function __construct()
    {
        $secretKey = config('stripe.secret');

        if (!$secretKey) {
            throw new Exception('Stripe secret key is not configured. Please set STRIPE_SECRET_KEY in .env');
        }

        $this->stripe = new StripeClient($secretKey);
        $this->webhookSecret = config('stripe.webhook_secret', '');
    }

    /**
     * Create or get a Stripe customer for a user.
     */
    public function getOrCreateCustomer(User $user): Customer
    {
        // If customer already exists, return it
        if ($user->stripe_customer_id) {
            return $this->stripe->customers->retrieve($user->stripe_customer_id);
        }

        // Create new customer
        $customer = $this->stripe->customers->create([
            'name' => $user->name,
            'email' => $user->email,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);

        // Save customer ID
        $user->update(['stripe_customer_id' => $customer->id]);

        return $customer;
    }

    /**
     * Create a checkout session for subscription upgrade.
     */
    public function createCheckoutSession(User $user, string $planCode): string
    {
        $customer = $this->getOrCreateCustomer($user);
        $plan = SubscriptionPlan::getByCode($planCode);

        if (!$plan) {
            throw new Exception("Invalid plan: $planCode");
        }

        $session = $this->stripe->checkout->sessions->create([
            'customer' => $customer->id,
            'payment_method_types' => ['card'],
            'mode' => $plan->price > 0 ? 'subscription' : 'setup',
            'success_url' => env('APP_FRONTEND_URL') . '/subscription/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => env('APP_FRONTEND_URL') . '/subscription/cancel',
            'line_items' => $this->getLineItems($plan),
            'metadata' => [
                'user_id' => $user->id,
                'plan_code' => $planCode,
                'plan_id' => $plan->id,
            ],
        ]);

        return $session->url;
    }

    /**
     * Create a billing portal session for customer to manage subscriptions.
     */
    public function createPortalSession(User $user): string
    {
        $customer = $this->getOrCreateCustomer($user);

        $session = $this->stripe->billingPortal->sessions->create([
            'customer' => $customer->id,
            'return_url' => env('APP_FRONTEND_URL') . '/subscription',
        ]);

        return $session->url;
    }

    /**
     * Get line items for checkout session.
     */
    private function getLineItems(SubscriptionPlan $plan): array
    {
        if ($plan->price == 0) {
            return [];
        }

        return [
            [
                'price_data' => [
                    'currency' => strtolower($plan->currency),
                    'product_data' => [
                        'name' => $plan->name,
                        'description' => $this->describePlan($plan),
                    ],
                    'unit_amount' => (int)($plan->price * 100), // Convert to cents
                    'recurring' => [
                        'interval' => $plan->interval,
                        'interval_count' => 1,
                    ],
                ],
                'quantity' => 1,
            ],
        ];
    }

    /**
     * Describe a plan for display purposes.
     */
    private function describePlan(SubscriptionPlan $plan): string
    {
        $features = [];
        if ($plan->features && is_array($plan->features)) {
            foreach ($plan->features as $feature) {
                $features[] = $feature;
            }
        }

        return implode(' | ', $features);
    }

    /**
     * Create or update a subscription in Stripe.
     */
    public function createOrUpdateSubscription(User $user, string $planCode): StripeSubscription
    {
        $customer = $this->getOrCreateCustomer($user);
        $plan = SubscriptionPlan::getByCode($planCode);

        if (!$plan) {
            throw new Exception("Invalid plan: $planCode");
        }

        // If already has subscription and plan allows upgrade/downgrade
        if ($user->subscription && $user->stripe_subscription_id) {
            return $this->updateSubscription($user, $planCode);
        }

        // Get or create price for plan
        $priceId = $this->getOrCreatePrice($plan);

        if (!$priceId) {
            throw new Exception("Could not create/get price for plan: $planCode");
        }

        // Create new subscription
        $subscription = $this->stripe->subscriptions->create([
            'customer' => $customer->id,
            'items' => [
                [
                    'price' => $priceId,
                ],
            ],
            'metadata' => [
                'user_id' => $user->id,
                'plan_code' => $planCode,
                'plan_id' => $plan->id,
            ],
        ]);

        // Update user with subscription
        if ($user->subscription) {
            $user->subscription->update([
                'plan' => $planCode,
                'stripe_subscription_id' => $subscription->id,
                'status' => 'active',
                'current_period_start' => $subscription->current_period_start,
                'current_period_end' => $subscription->current_period_end,
            ]);
        }

        return $subscription;
    }

    /**
     * Update an existing subscription to a new plan.
     */
    public function updateSubscription(User $user, string $newPlanCode): StripeSubscription
    {
        if (!$user->stripe_subscription_id) {
            return $this->createOrUpdateSubscription($user, $newPlanCode);
        }

        $subscription = $this->stripe->subscriptions->retrieve($user->stripe_subscription_id);
        $newPlan = SubscriptionPlan::getByCode($newPlanCode);

        if (!$newPlan) {
            throw new Exception("Invalid plan: $newPlanCode");
        }

        $newPriceId = $this->getOrCreatePrice($newPlan);

        // Update subscription
        $updatedSubscription = $this->stripe->subscriptions->update($user->stripe_subscription_id, [
            'items' => [
                [
                    'id' => $subscription->items->data[0]->id,
                    'price' => $newPriceId,
                ],
            ],
            'metadata' => [
                'plan_code' => $newPlanCode,
                'plan_id' => $newPlan->id,
            ],
        ]);

        // Update local subscription
        if ($user->subscription) {
            $user->subscription->update([
                'plan' => $newPlanCode,
            ]);
        }

        return $updatedSubscription;
    }

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(User $user, bool $immediately = false): ?StripeSubscription
    {
        if (!$user->stripe_subscription_id) {
            return null;
        }

        $params = $immediately ? [] : ['cancel_at_period_end' => true];

        $subscription = $this->stripe->subscriptions->update(
            $user->stripe_subscription_id,
            $params
        );

        // Update local subscription
        if ($user->subscription) {
            $user->subscription->update([
                'status' => $immediately ? 'canceled' : 'active',
                'canceled_at' => $immediately ? now() : null,
            ]);
        }

        return $subscription;
    }

    /**
     * Reactivate a canceled subscription.
     */
    public function reactivateSubscription(User $user): StripeSubscription
    {
        if (!$user->stripe_subscription_id) {
            throw new Exception('User does not have an active subscription');
        }

        $subscription = $this->stripe->subscriptions->update($user->stripe_subscription_id, [
            'cancel_at_period_end' => false,
        ]);

        // Update local subscription
        if ($user->subscription) {
            $user->subscription->update([
                'status' => 'active',
                'canceled_at' => null,
            ]);
        }

        return $subscription;
    }

    /**
     * Get or create a Stripe price for a plan.
     */
    private function getOrCreatePrice(SubscriptionPlan $plan): ?string
    {
        if ($plan->price == 0) {
            return null;
        }

        // For simplicity, we'll use product ID format: plan_code
        // In production, you'd want to store price IDs in database
        $productId = "prod_{$plan->code}";
        $priceDescription = "{$plan->name} - {$plan->currency} {$plan->price}/month";

        // This is a simplified approach - in production, query Stripe API for existing prices
        // For now, return a placeholder that should be stored in database
        return "price_{$plan->code}_" . md5($priceDescription);
    }

    /**
     * Process webhook event.
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $this->webhookSecret
            );
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Invalid signature',
            ];
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event->data->object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($event->data->object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
            default => null,
        };

        return [
            'success' => true,
            'message' => 'Webhook processed',
        ];
    }

    /**
     * Handle checkout session completed webhook.
     */
    private function handleCheckoutSessionCompleted($session): void
    {
        $metadata = $session->metadata;
        $user = User::find($metadata->user_id);

        if (!$user) {
            return;
        }

        // Update user stripe customer id if not already set
        if (!$user->stripe_customer_id) {
            $user->update(['stripe_customer_id' => $session->customer]);
        }

        // Get or create subscription
        $subscription = $user->subscription() ?? $user->subscriptions()->first();

        if ($session->mode === 'subscription' && $session->subscription) {
            $planCode = $metadata->plan_code ?? 'free';

            if (!$subscription) {
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'plan' => $planCode,
                    'stripe_subscription_id' => $session->subscription,
                    'status' => 'active',
                ]);
            } else {
                $subscription->update([
                    'stripe_subscription_id' => $session->subscription,
                    'plan' => $planCode,
                    'status' => 'active',
                ]);
            }
        }
    }

    /**
     * Handle subscription updated webhook.
     */
    private function handleSubscriptionUpdated($subscription): void
    {
        $user = User::where('stripe_subscription_id', $subscription->id)->first();

        if (!$user) {
            return;
        }

        $subscription_record = $user->subscription();

        if ($subscription_record) {
            $subscription_record->update([
                'status' => $subscription->status,
                'current_period_start' => $subscription->current_period_start,
                'current_period_end' => $subscription->current_period_end,
            ]);
        }
    }

    /**
     * Handle subscription deleted webhook.
     */
    private function handleSubscriptionDeleted($subscription): void
    {
        $user = User::where('stripe_subscription_id', $subscription->id)->first();

        if (!$user) {
            return;
        }

        $subscription_record = $user->subscription();

        if ($subscription_record) {
            $subscription_record->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'plan' => 'free', // Downgrade to free plan
            ]);
        }
    }

    /**
     * Handle invoice payment succeeded webhook.
     */
    private function handleInvoicePaymentSucceeded($invoice): void
    {
        $user = User::where('stripe_customer_id', $invoice->customer)->first();

        if (!$user) {
            return;
        }

        // Create or update subscription invoice record
        SubscriptionInvoice::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'user_id' => $user->id,
                'amount' => $invoice->amount_paid / 100, // Convert from cents
                'currency' => strtoupper($invoice->currency),
                'status' => 'paid',
                'paid_at' => $invoice->paid_at ? now()->setTimestamp($invoice->paid_at) : now(),
            ]
        );
    }

    /**
     * Handle invoice payment failed webhook.
     */
    private function handleInvoicePaymentFailed($invoice): void
    {
        $user = User::where('stripe_customer_id', $invoice->customer)->first();

        if (!$user) {
            return;
        }

        // Create or update subscription invoice record
        SubscriptionInvoice::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'user_id' => $user->id,
                'amount' => $invoice->amount_due / 100, // Convert from cents
                'currency' => strtoupper($invoice->currency),
                'status' => 'failed',
            ]
        );
    }

    /**
     * Retrieve a customer from Stripe.
     */
    public function getCustomer(User $user): ?Customer
    {
        if (!$user->stripe_customer_id) {
            return null;
        }

        return $this->stripe->customers->retrieve($user->stripe_customer_id);
    }

    /**
     * List invoices for a customer.
     */
    public function listInvoices(User $user, int $limit = 10): array
    {
        $customer = $this->getOrCreateCustomer($user);

        $invoices = $this->stripe->invoices->all(['customer' => $customer->id, 'limit' => $limit]);

        return $invoices->data ?? [];
    }

    /**
     * Get subscription details.
     */
    public function getSubscriptionDetails(User $user): ?StripeSubscription
    {
        if (!$user->stripe_subscription_id) {
            return null;
        }

        return $this->stripe->subscriptions->retrieve($user->stripe_subscription_id);
    }
}

