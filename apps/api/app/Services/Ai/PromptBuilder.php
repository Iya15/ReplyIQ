<?php

namespace App\Services\Ai;

use App\DataObjects\RetrievedChunk;
use App\Models\Chatbot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

class PromptBuilder
{
    private const HISTORY_LIMIT = 6;

    private const NO_CONTEXT_MARKER = '[no relevant context found]';

    /**
     * Build the messages array for a chat completion request.
     *
     * Message structure:
     *  [0]   system  — rendered Blade template with chatbot persona + context chunks
     *  [1..N] user/assistant — last HISTORY_LIMIT turns from prior conversation
     *  [N+1] user   — current query wrapped in <<<USER>>>...<<<END>>> delimiters
     *
     * @param  Collection<int, RetrievedChunk>                     $chunks
     * @param  array<int, array{role: string, content: string}>    $history  Most-recent last.
     * @return array<int, array{role: string, content: string}>
     */
    public function build(
        Chatbot $chatbot,
        string $query,
        Collection $chunks,
        array $history = [],
    ): array {
        $settings = $chatbot->settings;
        $org      = $chatbot->organization;

        $chunksJoined = $chunks->isNotEmpty()
            ? $chunks->map(fn (RetrievedChunk $c) => $c->content)->implode("\n\n---\n\n")
            : self::NO_CONTEXT_MARKER;

        $template     = File::get(resource_path('prompts/system.blade.php'));
        $systemPrompt = Blade::render($template, [
            'chatbot_name'            => $chatbot->name,
            'organization_name'       => $org->name,
            'ai_tone'                 => $settings?->ai_tone ?? 'professional and friendly',
            'ai_persona'              => $settings?->ai_persona ?? '',
            'fallback_message'        => $settings?->fallback_message
                ?? "I'm sorry, I don't have enough information to answer that question.",
            'retrieved_chunks_joined' => $chunksJoined,
        ]);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach (array_slice($history, -self::HISTORY_LIMIT) as $turn) {
            $messages[] = $turn;
        }

        $messages[] = [
            'role'    => 'user',
            'content' => "<<<USER>>>\n{$query}\n<<<END>>>",
        ];

        return $messages;
    }
}
