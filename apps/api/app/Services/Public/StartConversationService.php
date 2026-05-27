<?php

namespace App\Services\Public;

use App\Events\ConversationStarted;
use App\Models\Chatbot;
use App\Models\Conversation;

class StartConversationService
{
    /**
     * Create a new conversation for an anonymous widget visitor.
     *
     * Geo-location is deferred — the IP is stored and country will be resolved
     * in a background job once MaxMind GeoLite2 integration is added (Phase 4+).
     */
    public function execute(
        Chatbot  $chatbot,
        string   $visitorId,
        ?string  $sourceUrl,
        ?string  $userAgent,
        ?string  $ip,
    ): Conversation {
        $conversation = Conversation::create([
            'organization_id' => $chatbot->organization_id,
            'chatbot_id'      => $chatbot->id,
            'visitor_id'      => $visitorId,
            'source_url'      => $sourceUrl,
            'user_agent'      => $userAgent,
            'ip_address'      => $ip,
            // TODO: resolve country from IP using MaxMind GeoLite2 (Phase 4)
        ]);

        ConversationStarted::dispatch($conversation);

        return $conversation;
    }
}
