<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Organization extends Model
{
    use Billable, HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'trial_ends_at',
        'settings',
        'stripe_id',
        'pm_type',
        'pm_last_four',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')
            ->using(Membership::class)
            ->withPivot('role', 'created_at');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    // Chatbot model is created in Milestone 1.5; Eloquent resolves this lazily.
    public function chatbots(): HasMany
    {
        return $this->hasMany(Chatbot::class);
    }
}
