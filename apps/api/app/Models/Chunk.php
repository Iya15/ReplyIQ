<?php

namespace App\Models;

use App\Casts\VectorCast;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chunk extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    // Chunks are immutable once created; no updated_at column.
    const UPDATED_AT = null;

    protected $fillable = [
        'chatbot_id',
        'document_id',
        'chunk_index',
        'content',
        'token_count',
        'embedding',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'embedding' => VectorCast::class,
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }
}
