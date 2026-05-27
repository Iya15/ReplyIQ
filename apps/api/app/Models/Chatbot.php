<?php

namespace App\Models;

use App\Enums\ChatbotStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Observers\ChatbotObserver;
use App\Support\PublicIdGenerator;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy(ChatbotObserver::class)]
class Chatbot extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'public_id',
        'status',
        'language',
    ];

    protected static function boot(): void
    {
        parent::boot(); // triggers bootBelongsToTenant() and registers the observer

        static::creating(function (Chatbot $chatbot): void {
            if (empty($chatbot->public_id)) {
                $chatbot->public_id = PublicIdGenerator::generate();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => ChatbotStatus::class,
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    // organization() is provided by BelongsToTenant trait.

    public function settings(): HasOne
    {
        return $this->hasOne(ChatbotSettings::class, 'chatbot_id');
    }

    // Forward reference — Document model added in M2.
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    // Forward reference — Conversation model added in M3.
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function regenerateEmbedCode(): string
    {
        return <<<HTML
<script>
  (function(d,s,o,f,js,fjs){
    d['ReplyIQ']=o;d[o]=d[o]||function(){(d[o].q=d[o].q||[]).push(arguments)};
    js=d.createElement(s);fjs=d.getElementsByTagName(s)[0];
    js.id=o;js.src=f;js.async=1;fjs.parentNode.insertBefore(js,fjs);
  }(document,'script','riq','https://cdn.replyiq.com/widget.js'));
  riq('init', { chatbotId: '{$this->public_id}' });
</script>
HTML;
    }
}
