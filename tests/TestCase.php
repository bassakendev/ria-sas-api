<?php

namespace Tests;

use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Stripe\Customer;
use Stripe\Subscription;

// Create a fake Subscription class that passes type checking
class FakeSubscription extends Subscription {}

class FakeStripeService extends StripeService
{
    public function __construct() {}

    public function getOrCreateCustomer(User $user): Customer
    {
        $obj = (object)['id' => 'cus_test_123', 'email' => 'test@example.com'];
        return $obj;
    }

    public function createCheckoutSession(User $user, string $plan): string
    {
        return 'https://checkout.stripe.com/test';
    }

    public function createPortalSession(User $user): string
    {
        return 'https://billing.stripe.com/test';
    }

    public function cancelSubscription(User $user, bool $immediately = false): ?Subscription
    {
        $obj = new FakeSubscription();
        return $obj;
    }

    public function updateSubscription(User $user, string $newPlan): Subscription
    {
        $obj = new FakeSubscription();
        return $obj;
    }

    public function reactivateSubscription(User $user): Subscription
    {
        $obj = new FakeSubscription();
        return $obj;
    }

    public function getSubscriptionDetails(User $user): ?Subscription
    {
        $obj = new FakeSubscription();
        return $obj;
    }

    public function getCustomer(User $user): ?Customer
    {
        $obj = (object)['id' => 'cus_test_123', 'name' => 'Test User', 'email' => 'test@example.com'];
        return $obj;
    }

    public function listInvoices(User $user, int $limit = 10): array
    {
        return [];
    }
}

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Bind fake StripeService for tests
        $this->app->singleton(StripeService::class, FakeStripeService::class);
    }
}
