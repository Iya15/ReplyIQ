<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property ConversationStatus $status
 * @property Carbon|null $resolved_at
 */
class Conversation extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    // Conversations have no created_at set by Laravel — the DB default handles it.
    // updated_at is managed normally.
    protected $fillable = [
        'chatbot_id',
        'visitor_id',
        'visitor_email',
        'visitor_name',
        'source_url',
        'user_agent',
        'ip_address',
        'country',
        'status',
        'resolved_at',
        'agent_id',
        'escalated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'resolved_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    // organization() provided by BelongsToTenant.

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('created_at');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
