<?php

// @requires PostgreSQL (CI/Docker only)

use App\Billing\Plans;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\Billing\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\WebhookHandled;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function billingOrg(string $plan = Plans::FREE): array
{
    $org  = Organization::factory()->create(['plan' => $plan]);
    $user = User::factory()->create();
    Membership::factory()->create(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner']);
    return compact('org', 'user');
}

function billingHeaders(User $user): array
{
    return ['Authorization' => 'Bearer ' . $user->createToken('test')->plainTextToken];
}

// ── Plans ─────────────────────────────────────────────────────────────────────

it('Plans::get returns the correct limits for each tier', function () {
    expect(Plans::get(Plans::FREE)['chatbots'])->toBe(1);
    expect(Plans::get(Plans::STARTER)['chatbots'])->toBe(5);
    expect(Plans::get(Plans::PRO)['chatbots'])->toBe(20);
    expect(Plans::get(Plans::BUSINESS)['chatbots'])->toBe(-1);
});

it('Plans::get falls back to free limits for unknown plan', function () {
    expect(Plans::get('unknown')['chatbots'])->toBe(1);
});

it('Plans::fromPriceId returns FREE for unconfigured price', function () {
    expect(Plans::fromPriceId('price_unknown'))->toBe(Plans::FREE);
});

// ── PlanLimits ────────────────────────────────────────────────────────────────

it('PlanLimits::check returns true when under the limit', function () {
    ['org' => $org] = billingOrg(Plans::STARTER);
    $limits = app(PlanLimits::class);

    // Starter allows 5 chatbots; org has 0.
    expect($limits->check($org, 'chatbots'))->toBeTrue();
});

it('PlanLimits::check returns true for unlimited plans', function () {
    ['org' => $org] = billingOrg(Plans::BUSINESS);
    $limits = app(PlanLimits::class);

    expect($limits->check($org, 'chatbots'))->toBeTrue();
    expect($limits->check($org, 'team_size'))->toBeTrue();
});

it('PlanLimits::remaining returns the correct value', function () {
    ['org' => $org] = billingOrg(Plans::FREE);
    $limits = app(PlanLimits::class);

    // Free plan: chatbots limit = 1, current usage = 0.
    expect($limits->remaining($org, 'chatbots'))->toBe(1);
});

// ── GET /billing/subscription ─────────────────────────────────────────────────

it('returns current plan and usage summary', function () {
    ['org' => $org, 'user' => $user] = billingOrg();

    $this->withHeaders(billingHeaders($user))
        ->getJson('/api/v1/billing/subscription')
        ->assertOk()
        ->assertJsonStructure(['data' => ['plan', 'subscription', 'usage', 'limits']])
        ->assertJsonPath('data.plan', Plans::FREE);
});

it('usage includes chatbots, documents, team_size, messages_per_month', function () {
    ['user' => $user] = billingOrg();

    $response = $this->withHeaders(billingHeaders($user))
        ->getJson('/api/v1/billing/subscription')
        ->assertOk();

    $usage = $response->json('data.usage');
    expect($usage)->toHaveKeys(['chatbots', 'documents', 'team_size', 'messages_per_month']);
});

// ── EnforcePlanLimit middleware ───────────────────────────────────────────────

it('creating a chatbot when at the free limit returns 402', function () {
    ['org' => $org, 'user' => $user] = billingOrg(Plans::FREE);

    // Create one chatbot (hitting free limit of 1).
    \App\Models\Chatbot::factory()->create(['organization_id' => $org->id, 'status' => 'active']);

    $this->withHeaders(billingHeaders($user))
        ->postJson('/api/v1/chatbots', ['name' => 'Over limit'])
        ->assertStatus(402)
        ->assertJsonPath('error.code', 'plan_limit_exceeded');
});

it('creating a chatbot within limits is allowed', function () {
    ['user' => $user] = billingOrg(Plans::STARTER);

    $this->withHeaders(billingHeaders($user))
        ->postJson('/api/v1/chatbots', ['name' => 'My bot'])
        ->assertCreated();
});

// ── SyncSubscriptionPlan listener ─────────────────────────────────────────────

it('syncs plan to free when subscription is deleted', function () {
    ['org' => $org] = billingOrg(Plans::PRO);
    $org->update(['stripe_id' => 'cus_test123']);

    $event = new WebhookHandled([
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => ['customer' => 'cus_test123', 'status' => 'canceled']],
    ]);

    app(\App\Listeners\SyncSubscriptionPlan::class)->handle($event);

    expect($org->fresh()->plan)->toBe(Plans::FREE);
});

it('syncs plan from stripe price on subscription updated', function () {
    config(['billing.prices.pro_monthly' => 'price_pro_123']);

    ['org' => $org] = billingOrg(Plans::FREE);
    $org->update(['stripe_id' => 'cus_test456']);

    $event = new WebhookHandled([
        'type' => 'customer.subscription.updated',
        'data' => ['object' => [
            'customer' => 'cus_test456',
            'status'   => 'active',
            'items'    => ['data' => [['price' => ['id' => 'price_pro_123']]]],
        ]],
    ]);

    app(\App\Listeners\SyncSubscriptionPlan::class)->handle($event);

    expect($org->fresh()->plan)->toBe(Plans::PRO);
});
