<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $org = $request->user()->currentOrganization();

        // Every authenticated user must belong to at least one organization.
        // This should not happen in practice (registration always creates one),
        // but we abort defensively rather than silently querying without a tenant.
        abort_if($org === null, 403, 'No organization associated with this account.');

        app()->instance('currentOrganization', $org);

        return $next($request);
    }
}
