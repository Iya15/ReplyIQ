<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Billing\Plans;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Billing\PlanLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    public function __construct(private readonly PlanLimits $limits) {}

    /** GET /billing/subscription — current plan, usage, and subscription state. */
    public function subscription(Request $r): JsonResponse
    {
        /** @var Organization $org */
        $org  = app('currentOrganization');
        $plan = Plans::get($org->plan);
        $sub  = $org->subscription();

        $usage = [];
        foreach (['chatbots', 'documents', 'team_size', 'messages_per_month'] as $metric) {
            $limit = $plan[$metric] ?? -1;
            $usage[$metric] = [
                'current' => $this->limits->currentUsage($org, $metric),
                'limit'   => $limit,
            ];
        }

        return $this->ok([
            'plan'         => $org->plan,
            'subscription' => $sub ? [
                'stripe_status'          => $sub->stripe_status,
                'current_period_end'     => $sub->ends_at,
                'cancel_at_period_end'   => $sub->ends_at !== null,
            ] : null,
            'usage'        => $usage,
            'limits'       => $plan,
        ], $r);
    }

    /** POST /billing/checkout-session — create a Stripe Checkout session URL. */
    public function checkoutSession(Request $r): JsonResponse
    {
        $r->validate(['price_id' => ['required', 'string']]);

        /** @var Organization $org */
        $org = app('currentOrganization');

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        $checkout = $org->newSubscription('default', (string) $r->input('price_id'))
            ->checkout([
                'success_url' => "{$frontendUrl}/billing?success=1",
                'cancel_url'  => "{$frontendUrl}/billing",
            ]);

        return $this->ok(['url' => $checkout->asStripeCheckoutSession()->url], $r);
    }

    /** POST /billing/portal-session — create a Stripe Customer Portal session URL. */
    public function portalSession(Request $r): JsonResponse
    {
        /** @var Organization $org */
        $org = app('currentOrganization');

        if (! $org->stripe_id) {
            return $this->error('no_subscription', 'No billing account found. Subscribe first.', $r, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $url = $org->billingPortalUrl("{$frontendUrl}/billing");

        return $this->ok(['url' => $url], $r);
    }
}
