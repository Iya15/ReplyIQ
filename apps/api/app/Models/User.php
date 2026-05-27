<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, HasUuids, MustVerifyEmail, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'avatar_url',
        // password_hash is set explicitly; never mass-assigned as plain text.
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_hash' => 'hashed',
        ];
    }

    // Tell Laravel's Auth and Sanctum which column holds the password hash.
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'memberships')
            ->using(Membership::class)
            ->withPivot('role');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns the user's active organization.
     *
     * Stub: always returns the first membership's organization.
     * Multi-org switching (stored in session or JWT claim) will replace this
     * in the auth middleware milestone.
     */
    public function currentOrganization(): ?Organization
    {
        return $this->organizations()->first();
    }
}
