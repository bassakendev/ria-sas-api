<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $table = 'subscription_plans';

    protected $fillable = [
        'code',
        'name',
        'price',
        'currency',
        'interval',
        'features',
        'limits',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'limits' => 'array',
    ];

    /**
     * Get the subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /**
     * Get a plan by code.
     */
    public static function getByCode(string $code): ?self
    {
        return self::where('code', $code)->first();
    }

    /**
     * Get all plans.
     */
    public static function getAllPlans(): array
    {
        return self::all()->mapWithKeys(fn ($plan) => [
            $plan->code => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => (float) $plan->price,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
                'features' => $plan->features,
                'limits' => $plan->limits,
            ],
        ])->toArray();
    }

    /**
     * Check if this is the free plan.
     */
    public function isFree(): bool
    {
        return $this->code === 'free';
    }

    /**
     * Check if this is the pro plan.
     */
    public function isPro(): bool
    {
        return $this->code === 'pro';
    }
}
