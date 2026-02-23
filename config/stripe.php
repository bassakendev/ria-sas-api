<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe Keys
    |--------------------------------------------------------------------------
    */
    'secret' => env('STRIPE_SECRET_KEY'),
    'public' => env('STRIPE_PUBLIC_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Stripe Plans Configuration
    | Plans are now managed in the database via SubscriptionPlan model.
    | Use SubscriptionPlan::getAllPlans() to retrieve them.
    |--------------------------------------------------------------------------
    */
    'plans' => [], // Deprecated: Plans are now in the database

    /*
    |--------------------------------------------------------------------------
    | Retry Settings
    |--------------------------------------------------------------------------
    */
    'retry_delay' => 1000, // milliseconds
    'max_retries' => 3,
];

