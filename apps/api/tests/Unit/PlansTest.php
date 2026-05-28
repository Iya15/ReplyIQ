<?php

use App\Billing\Plans;

it('Plans::all returns all four plan slugs', function () {
    expect(Plans::all())->toBe(['free', 'starter', 'pro', 'business']);
});

it('Plans::get returns the free plan limits for unknown input', function () {
    $limits = Plans::get('unknown_plan');
    expect($limits['chatbots'])->toBe(1);
});

it('free plan has expected limits', function () {
    $limits = Plans::get(Plans::FREE);
    expect($limits['chatbots'])->toBe(1)
        ->and($limits['messages_per_month'])->toBe(500)
        ->and($limits['documents'])->toBe(10)
        ->and($limits['team_size'])->toBe(1);
});

it('business plan has unlimited (-1) on all numeric limits', function () {
    $limits = Plans::get(Plans::BUSINESS);
    expect($limits['chatbots'])->toBe(-1)
        ->and($limits['messages_per_month'])->toBe(-1)
        ->and($limits['documents'])->toBe(-1)
        ->and($limits['team_size'])->toBe(-1);
});

it('Plans::fromPriceId returns FREE for empty or unknown price ID', function () {
    expect(Plans::fromPriceId(''))->toBe(Plans::FREE);
    expect(Plans::fromPriceId('price_nonexistent'))->toBe(Plans::FREE);
});

it('Plans::fromPriceId maps configured price ID to correct plan', function () {
    config(['billing.prices.pro_monthly' => 'price_pro_test_123']);
    expect(Plans::fromPriceId('price_pro_test_123'))->toBe(Plans::PRO);
});

it('Plans::fromPriceId maps annual price to the same plan as monthly', function () {
    config([
        'billing.prices.starter_monthly' => 'price_starter_m',
        'billing.prices.starter_annual' => 'price_starter_y',
    ]);
    expect(Plans::fromPriceId('price_starter_m'))->toBe(Plans::STARTER);
    expect(Plans::fromPriceId('price_starter_y'))->toBe(Plans::STARTER);
});
