<?php

namespace App\Models;

use App\Casts\PostgresArray;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotSettings extends Model
{
    use HasFactory;

    // chatbot_id IS the primary key — no separate UUID.
    protected $primaryKey = 'chatbot_id';

    public $incrementing = false;

    protected $keyType = 'string';

    // The table has only updated_at (no created_at).
    const CREATED_AT = null;

    protected $fillable = [
        'logo_url',
        'avatar_url',
        'primary_color',
        'text_color',
        'font_family',
        'welcome_message',
        'placeholder_text',
        'ai_tone',
        'ai_persona',
        'position',
        'theme',
        'show_branding',
        'model',
        'temperature',
        'max_tokens',
        'similarity_threshold',
        'retrieval_k',
        'fallback_message',
        'allowed_domains',
    ];

    protected function casts(): array
    {
        return [
            'show_branding' => 'boolean',
            'temperature' => 'float',
            'similarity_threshold' => 'float',
            'allowed_domains' => PostgresArray::class,
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class, 'chatbot_id');
    }
}
