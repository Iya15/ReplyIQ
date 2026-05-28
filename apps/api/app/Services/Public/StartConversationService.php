<?php

namespace App\Services\Public;

use App\Events\ConversationStarted;
use App\Models\Chatbot;
use App\Models\Conversation;
use App\Services\Analytics\AnalyticsRecorder;

class StartConversationService
{
    public function __construct(private readonly AnalyticsRecorder $analytics) {}

    /**
     * Create a new conversation for an anonymous widget visitor.
     *
     * Geo-location is deferred — the IP is stored and country will be resolved
     * in a background job once MaxMind GeoLite2 integration is added (Phase 4+).
     */
    public function execute(
        Chatbot $chatbot,
        string $visitorId,
        ?string $sourceUrl,
        ?string $userAgent,
        ?string $ip,
    ): Conversation {
        $conversation = Conversation::create([
            'organization_id' => $chatbot->organization_id,
            'chatbot_id' => $chatbot->id,
            'visitor_id' => $visitorId,
            'source_url' => $sourceUrl,
            'user_agent' => $userAgent,
            'ip_address' => $ip,
            // TODO: resolve country from IP using MaxMind GeoLite2 (Phase 4)
        ]);

        ConversationStarted::dispatch($conversation);

        $this->analytics->record(
            eventType: 'conversation_started',
            organizationId: (string) $chatbot->organization_id,
            chatbotId: (string) $chatbot->id,
            conversationId: (string) $conversation->id,
            context: [
                'visitor_id' => $visitorId,
                'source_url' => $sourceUrl,
            ],
        );

        return $conversation;
    }
}
