<?php

namespace App\Billing;

/**
 * Central registry of subscription plans and their resource limits.
 *
 * Limits:
 *   -1 = unlimited
 *
 * Stripe price IDs are read from config('billing.prices.*') which maps
 * to STRIPE_PRICE_* env vars. Set them after creating products/prices in
 * the Stripe dashboard.
 */
class Plans
{
    public const FREE = 'free';

    public const STARTER = 'starter';

    public const PRO = 'pro';

    public const BUSINESS = 'business';

    /** @var array<string, array<string, mixed>> */
    public const LIMITS = [
        self::FREE => [
            'chatbots' => 1,
            'messages_per_month' => 500,
            'documents' => 10,
            'team_size' => 1,
            'allowed_models' => ['gpt-4o-mini'],
            'ai_provider' => 'openai',
        ],
        self::STARTER => [
            'chatbots' => 5,
            'messages_per_month' => 5_000,
            'documents' => 100,
            'team_size' => 3,
            'allowed_models' => ['gpt-4o-mini', 'gpt-4o'],
            'ai_provider' => 'openai',
        ],
        self::PRO => [
            'chatbots' => 20,
            'messages_per_month' => 25_000,
            'documents' => 500,
            'team_size' => 10,
            'allowed_models' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo'],
            'ai_provider' => 'openai',
        ],
        self::BUSINESS => [
            'chatbots' => -1,
            'messages_per_month' => -1,
            'documents' => -1,
            'team_size' => -1,
            'allowed_models' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo'],
            'ai_provider' => 'openai',
        ],
    ];

    /** Monthly display prices in USD cents. */
    public const DISPLAY_PRICES = [
        self::FREE => 0,
        self::STARTER => 2900,
        self::PRO => 8900,
        self::BUSINESS => 24900,
    ];

    /** @return array<string, mixed> */
    public static function get(string $plan): array
    {
        return self::LIMITS[$plan] ?? self::LIMITS[self::FREE];
    }

    /** @return string[] */
    public static function all(): array
    {
        return [self::FREE, self::STARTER, self::PRO, self::BUSINESS];
    }

    /**
     * Map a Stripe price ID to a plan slug.
     * Falls back to 'free' if the price is not recognised.
     */
    public static function fromPriceId(string $priceId): string
    {
        /** @var array<string, string> $prices */
        $prices = config('billing.prices', []);

        $map = [
            ($prices['starter_monthly'] ?? '') => self::STARTER,
            ($prices['starter_annual'] ?? '') => self::STARTER,
            ($prices['pro_monthly'] ?? '') => self::PRO,
            ($prices['pro_annual'] ?? '') => self::PRO,
            ($prices['business_monthly'] ?? '') => self::BUSINESS,
            ($prices['business_annual'] ?? '') => self::BUSINESS,
        ];

        // Remove empty-string keys so an un-configured env doesn't shadow a real price.
        unset($map['']);

        return $map[$priceId] ?? self::FREE;
    }
}
