<?php

namespace App\Models;

use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property DocumentSourceType $source_type
 * @property DocumentStatus $status
 * @property array<string, mixed> $metadata
 * @property Carbon|null $processed_at
 */
class Document extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    // Follows blueprint DDL: no updated_at column.
    // processed_at tracks when ingestion completed (successfully or not).
    const UPDATED_AT = null;

    protected $fillable = [
        'chatbot_id',
        'source_type',
        'source_url',
        'title',
        'status',
        'error_message',
        'metadata',
        'char_count',
        'chunk_count',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => DocumentSourceType::class,
            'status' => DocumentStatus::class,
            'metadata' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }
}
