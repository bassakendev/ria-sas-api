<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'billing_period',
        'start_date',
        'next_billing_date',
        'trial_ends_at',
        'canceled_at',
        'stripe_subscription_id',
        'price',
    ];

    protected $casts = [
        'start_date' => 'date',
        'next_billing_date' => 'date',
        'trial_ends_at' => 'date',
        'canceled_at' => 'date',
        'price' => 'decimal:2',
    ];

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription plan details.
     */
    public function planDetails(): ?SubscriptionPlan
    {
        return SubscriptionPlan::getByCode($this->plan);
    }

    /**
     * Get the subscription invoices.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    /**
     * Upgrade to a new plan.
     */
    public function upgradeToPlan(string $planCode, string $billingPeriod = 'month'): void
    {
        $newPlan = SubscriptionPlan::getByCode($planCode);
        if (!$newPlan) {
            throw new Exception('Plan not found');
        }

        if ($this->plan === $planCode) {
            throw new Exception('Already on this plan');
        }

        $this->update([
            'plan' => $planCode,
            'billing_period' => $billingPeriod,
            'price' => $newPlan->price,
            'status' => 'active',
            'next_billing_date' => now()->addMonth(),
        ]);
    }

    /**
     * Downgrade to a new plan.
     */
    public function downgradeToPlan(string $planCode, ?string $effectiveDate = null): void
    {
        $newPlan = SubscriptionPlan::getByCode($planCode);
        if (!$newPlan) {
            throw new Exception('Plan not found');
        }

        if ($this->plan === $planCode) {
            throw new Exception('Already on this plan');
        }

        $this->update([
            'plan' => $planCode,
            'price' => $newPlan->price,
            'status' => 'active',
            'next_billing_date' => $effectiveDate ? \Carbon\Carbon::parse($effectiveDate) : now(),
        ]);
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(): void
    {
        $this->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);
    }

    /**
     * Reactivate the subscription.
     */
    public function reactivate(): void
    {
        if ($this->canceled_at && now()->diffInDays($this->canceled_at) > 30) {
            throw new Exception('Cannot reactivate after 30 days');
        }

        $this->update([
            'status' => 'active',
            'canceled_at' => null,
        ]);
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if subscription is pro.
     */
    public function isPro(): bool
    {
        return $this->plan === 'pro';
    }

    /**
     * Get plan pricing from DB.
     */
    public static function getPlanPrice(string $planCode, string $billingPeriod = 'month'): float
    {
        $plan = SubscriptionPlan::getByCode($planCode);
        return $plan ? (float) $plan->price : 0;
    }

    /**
     * Get plan details from DB.
     */
    public static function getPlanDetails(string $planCode): array
    {
        $plan = SubscriptionPlan::getByCode($planCode);

        if (!$plan) {
            return [];
        }

        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'price' => (float) $plan->price,
            'currency' => $plan->currency,
            'interval' => $plan->interval,
            'features' => $plan->features ?? [],
            'limits' => $plan->limits ?? [],
        ];
    }
}
