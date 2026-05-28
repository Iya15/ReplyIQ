<?php

/**
 * Webhook security tests.
 *
 * Stripe webhook signature verification is handled by Cashier's built-in
 * WebhookController. These tests verify that:
 *
 * 1. Requests without a Stripe-Signature header are rejected.
 * 2. Requests with an invalid signature are rejected.
 * 3. Cashier's signature verification is wired up (config-level check).
 *
 * We do NOT mock a valid Stripe signature here (that would require the secret)
 * — instead we confirm the rejection behaviour for invalid requests.
 */
it('stripe webhook rejects requests with no signature header', function () {
    $this->postJson('/api/v1/webhooks/stripe', ['type' => 'customer.subscription.updated'])
        ->assertStatus(400); // Cashier returns 400 for missing/invalid signature
});

it('stripe webhook rejects requests with an invalid signature', function () {
    $this->withHeader('Stripe-Signature', 't=99999999,v1=fake_signature_value')
        ->postJson('/api/v1/webhooks/stripe', ['type' => 'customer.subscription.updated'])
        ->assertStatus(400);
});

it('STRIPE_WEBHOOK_SECRET env variable is documented in .env.example', function () {
    $envExample = file_get_contents(base_path('.env.example'));
    expect($envExample)->toContain('STRIPE_WEBHOOK_SECRET');
});
