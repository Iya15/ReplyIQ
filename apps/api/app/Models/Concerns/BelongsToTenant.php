<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        // Auto-populate organization_id from the container binding on every
        // ->create() / ->save() for a new record.
        //
        // WHY this matters for security: every tenant-scoped model MUST have
        // organization_id set before it reaches the database. Without this hook,
        // a developer calling Model::create([...]) without 'organization_id' would
        // silently insert a row with a NULL foreign key — bypassing tenant isolation
        // entirely. By setting it here, the correct tenant is always applied even
        // when the caller forgets (or deliberately omits) the field.
        //
        // The hook is a no-op when organization_id is already set (explicit
        // creation in jobs, seeders, tests) or when no tenant is bound (console
        // commands that create cross-tenant records intentionally).
        static::creating(function (self $model): void {
            if (empty($model->organization_id) && app()->bound('currentOrganization')) {
                $model->organization_id = app('currentOrganization')->id;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
