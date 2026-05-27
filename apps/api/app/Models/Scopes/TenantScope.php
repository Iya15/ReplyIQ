<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // No binding = no filter. This intentionally covers three cases:
        //   1. Console commands that operate across all tenants (artisan commands).
        //   2. Queue jobs — they bind their own org via app()->instance() before
        //      querying, so those will still be filtered (see BelongsToTenant trait).
        //   3. Unauthenticated contexts where ResolveTenant never ran.
        if (! app()->bound('currentOrganization')) {
            return;
        }

        $builder->where($model->qualifyColumn('organization_id'), app('currentOrganization')->id);
    }
}
