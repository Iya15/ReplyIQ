<?php

namespace App\Http\Middleware;

use App\Billing\Plans;
use App\Models\Organization;
use App\Services\Billing\PlanLimits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a request with 402 Payment Required when an organization has
 * exhausted the quota for a specific metric on its current plan.
 *
 * Usage: ->middleware('plan-limit:chatbots')
 */
class EnforcePlanLimit
{
    public function __construct(private readonly PlanLimits $limits) {}

    public function handle(Request $request, Closure $next, string $metric): Response
    {
        /** @var Organization $org */
        $org = app('currentOrganization');

        if ($this->limits->check($org, $metric)) {
            return $next($request);
        }

        $plan   = $org->plan;
        $limits = Plans::get($plan);
        $limit  = $limits[$metric] ?? 0;

        return response()->json([
            'error' => [
                'code'    => 'plan_limit_exceeded',
                'message' => "Your {$plan} plan allows {$limit} {$metric}. Upgrade to create more.",
                'details' => [
                    'metric'  => $metric,
                    'limit'   => $limit,
                    'plan'    => $plan,
                    'current' => $this->limits->currentUsage($org, $metric),
                ],
            ],
        ], Response::HTTP_PAYMENT_REQUIRED);
    }
}
