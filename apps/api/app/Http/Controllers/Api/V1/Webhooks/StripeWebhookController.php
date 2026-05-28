<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

/**
 * Re-exposes Cashier's webhook handler at /api/v1/webhooks/stripe so it
 * lives next to our other API endpoints.
 *
 * Plan syncing is handled via the SyncSubscriptionPlan listener which fires
 * on Laravel\Cashier\Events\WebhookHandled after Cashier processes each event.
 */
class StripeWebhookController extends CashierWebhookController
{
    // All behaviour inherited from Cashier — no overrides needed.
}
