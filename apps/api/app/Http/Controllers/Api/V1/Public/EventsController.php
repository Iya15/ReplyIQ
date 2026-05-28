<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Chatbot;
use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EventsController extends Controller
{
    private const ALLOWED_EVENTS = ['widget_opened', 'widget_closed'];

    /**
     * POST /api/v1/public/events
     *
     * Receive widget lifecycle events (widget_opened, widget_closed) from the
     * loader script. Protected by origin check only (widget:config mode).
     */
    public function store(Request $request, AnalyticsRecorder $recorder): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['required', 'string', 'in:' . implode(',', self::ALLOWED_EVENTS)],
            'visitor_id' => ['nullable', 'string', 'max:128'],
            'source_url' => ['nullable', 'url', 'max:2048'],
        ]);

        /** @var Chatbot $chatbot */
        $chatbot = app('currentChatbot');

        $recorder->record(
            eventType:      $data['event_type'],
            organizationId: (string) $chatbot->organization_id,
            chatbotId:      (string) $chatbot->id,
            context: [
                'visitor_id' => $data['visitor_id'] ?? null,
                'source_url' => $data['source_url'] ?? null,
            ],
        );

        return response()->json(['ok' => true], Response::HTTP_ACCEPTED);
    }
}
