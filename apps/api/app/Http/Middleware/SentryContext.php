<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enriches every Sentry event with:
 *   - Authenticated user ID + email
 *   - Current organization ID + plan
 *
 * Applied globally so exceptions from any layer carry this context.
 * Must run AFTER auth middleware and tenant resolution.
 */
class SentryContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->bound('sentry')) {
            return $next($request);
        }

        \Sentry\configureScope(function (Scope $scope) use ($request): void {
            // Authenticated user context
            $user = $request->user();
            if ($user) {
                $scope->setUser([
                    'id'    => (string) $user->id,
                    'email' => (string) $user->email,
                ]);
                $scope->setTag('user.id', (string) $user->id);
            }

            // Tenant context
            if (app()->bound('currentOrganization')) {
                /** @var \App\Models\Organization $org */
                $org = app('currentOrganization');
                $scope->setTag('org.id',   (string) $org->id);
                $scope->setTag('org.plan', (string) $org->plan);
                $scope->setContext('organization', [
                    'id'   => (string) $org->id,
                    'name' => (string) $org->name,
                    'plan' => (string) $org->plan,
                ]);
            }
        });

        return $next($request);
    }
}
