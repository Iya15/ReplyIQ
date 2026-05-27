<?php

namespace App\Models;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property MessageRole   $role
 * @property MessageStatus $status
 * @property array<mixed>  $sources
 * @property \Illuminate\Support\Carbon $created_at
 */
class Message extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    // Messages have no updated_at — they're append-only.
    public $timestamps = false;

    /** @var string[] */
    protected $dates = ['created_at'];

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'status',
        'sources',
        'confidence',
        'tokens_used',
        'latency_ms',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'role'       => MessageRole::class,
            'status'     => MessageStatus::class,
            'sources'    => 'array',
            'confidence' => 'float',
            'created_at' => 'datetime',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    // organization() provided by BelongsToTenant.

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
