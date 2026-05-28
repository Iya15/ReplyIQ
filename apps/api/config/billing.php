<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe Price IDs
    |--------------------------------------------------------------------------
    | Set these in .env after creating products and prices in the Stripe
    | dashboard. Matching is done in App\Billing\Plans::fromPriceId().
    */
    'prices' => [
        'starter_monthly' => env('STRIPE_PRICE_STARTER_MONTHLY'),
        'starter_annual' => env('STRIPE_PRICE_STARTER_ANNUAL'),
        'pro_monthly' => env('STRIPE_PRICE_PRO_MONTHLY'),
        'pro_annual' => env('STRIPE_PRICE_PRO_ANNUAL'),
        'business_monthly' => env('STRIPE_PRICE_BUSINESS_MONTHLY'),
        'business_annual' => env('STRIPE_PRICE_BUSINESS_ANNUAL'),
    ],
];
