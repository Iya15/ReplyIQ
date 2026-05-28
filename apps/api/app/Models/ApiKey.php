<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string                       $id
 * @property string                       $organization_id
 * @property string|null                  $created_by
 * @property string                       $name
 * @property string                       $key_hash
 * @property string                       $prefix
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon   $created_at
 * @property string|null                  $plainKey  Transient — set after generation, never persisted.
 */
class ApiKey extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    public $timestamps = false;

    /** Transient plain-text key — set in memory after creation, never stored. */
    public ?string $plainKey = null;

    protected $fillable = [
        'name',
        'key_hash',
        'prefix',
        'created_by',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'created_at'   => 'datetime',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
