<?php

namespace App\Listeners;

use App\Billing\Plans;
use App\Models\Organization;
use Laravel\Cashier\Events\WebhookHandled;

/**
 * Keeps organizations.plan in sync with the active Stripe subscription.
 *
 * Fires after Cashier has already processed the webhook and updated its own
 * subscriptions table, so we can read the latest subscription state.
 */
class SyncSubscriptionPlan
{
    public function handle(WebhookHandled $event): void
    {
        $type    = $event->payload['type'] ?? '';
        $object  = $event->payload['data']['object'] ?? [];

        if (! in_array($type, [
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
        ], true)) {
            return;
        }

        $customerId = $object['customer'] ?? null;
        if (! $customerId) {
            return;
        }

        /** @var Organization|null $org */
        $org = Organization::where('stripe_id', $customerId)->first();
        if (! $org) {
            return;
        }

        if ($type === 'customer.subscription.deleted') {
            $org->update(['plan' => Plans::FREE]);
            return;
        }

        // Derive plan slug from the first subscription item's price ID.
        $priceId = $object['items']['data'][0]['price']['id'] ?? null;
        if (! $priceId) {
            return;
        }

        $plan = Plans::fromPriceId($priceId);

        // Only update if the subscription is in an active state.
        $status = $object['status'] ?? '';
        if (in_array($status, ['active', 'trialing'], true)) {
            $org->update(['plan' => $plan]);
        } elseif (in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            $org->update(['plan' => Plans::FREE]);
        }
    }
}
