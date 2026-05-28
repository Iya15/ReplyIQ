<?php

namespace App\Http\Controllers\Api\V1\Chatbots;

use App\Http\Controllers\Controller;
use App\Models\Chatbot;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    // ── Overview ──────────────────────────────────────────────────────────────

    public function overview(Request $r, Chatbot $chatbot): JsonResponse
    {
        $this->authorize('view', $chatbot);
        [$from, $to] = $this->parseRange($r);

        /** @var array<int, object> $rows */
        $rows = DB::select("
            SELECT
                COUNT(*) FILTER (WHERE event_type = 'conversation_started')                                     AS total_conversations,
                COUNT(*) FILTER (WHERE event_type = 'message_sent')                                             AS total_messages,
                COUNT(*) FILTER (WHERE event_type = 'widget_opened')                                            AS widget_opens,
                COUNT(*) FILTER (WHERE event_type = 'unanswered')                                               AS unanswered_count,
                ROUND(AVG(CASE WHEN event_type = 'message_replied' THEN (context->>'latency_ms')::float END))  AS avg_latency_ms,
                ROUND(AVG(CASE WHEN event_type = 'message_replied' THEN (context->>'confidence')::float END)::numeric, 2) AS avg_confidence
            FROM analytics_events
            WHERE chatbot_id      = ?
              AND organization_id = ?
              AND occurred_at BETWEEN ? AND ?
        ", [
            $chatbot->id,
            $chatbot->organization_id,
            $from->toDateTimeString(),
            $to->toDateTimeString(),
        ]);

        $s = $rows[0] ?? null;

        $totalConversations = (int) (($s->total_conversations ?? 0) ?: 0);
        $unansweredCount    = (int) (($s->unanswered_count ?? 0) ?: 0);

        $resolvedCount = Conversation::where('chatbot_id', $chatbot->id)
            ->where('status', 'resolved')
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->count();

        return $this->ok([
            'total_conversations' => $totalConversations,
            'total_messages'      => (int) (($s->total_messages ?? 0) ?: 0),
            'avg_latency_ms'      => $s && $s->avg_latency_ms !== null ? (int) $s->avg_latency_ms : null,
            'avg_confidence'      => $s && $s->avg_confidence !== null ? (float) $s->avg_confidence : null,
            'unanswered_rate'     => $totalConversations > 0
                ? round($unansweredCount / $totalConversations, 2)
                : 0.0,
            'funnel' => [
                'widget_opens'          => (int) (($s->widget_opens ?? 0) ?: 0),
                'conversations_started' => $totalConversations,
                'messages_sent'         => (int) (($s->total_messages ?? 0) ?: 0),
                'resolved'              => $resolvedCount,
            ],
        ], $r);
    }

    // ── Daily conversations time-series ───────────────────────────────────────

    public function conversations(Request $r, Chatbot $chatbot): JsonResponse
    {
        $this->authorize('view', $chatbot);
        [$from, $to] = $this->parseRange($r);

        /** @var array<int, object> $rows */
        $rows = DB::select("
            SELECT DATE(occurred_at)::text AS date, COUNT(*)::integer AS count
            FROM analytics_events
            WHERE event_type    = 'conversation_started'
              AND chatbot_id    = ?
              AND organization_id = ?
              AND occurred_at BETWEEN ? AND ?
            GROUP BY DATE(occurred_at)
            ORDER BY date ASC
        ", [
            $chatbot->id,
            $chatbot->organization_id,
            $from->toDateTimeString(),
            $to->toDateTimeString(),
        ]);

        $series = array_map(
            fn (object $row): array => ['date' => (string) $row->date, 'count' => (int) $row->count],
            $rows,
        );

        return $this->ok($series, $r);
    }

    // ── Top topics ────────────────────────────────────────────────────────────

    public function topics(Request $r, Chatbot $chatbot): JsonResponse
    {
        $this->authorize('view', $chatbot);
        [$from, $to] = $this->parseRange($r);

        /** @var array<int, object> $rows */
        $rows = DB::select("
            SELECT
                lower(regexp_replace(
                    split_part(coalesce(context->>'content_preview', ''), ' ', 1),
                    '[^a-z0-9]', '', 'g'
                )) AS topic,
                COUNT(*)::integer AS count
            FROM analytics_events
            WHERE event_type      = 'message_sent'
              AND chatbot_id      = ?
              AND organization_id = ?
              AND occurred_at BETWEEN ? AND ?
              AND (context->>'content_preview') IS NOT NULL
              AND length(coalesce(context->>'content_preview', '')) > 0
            GROUP BY topic
            HAVING lower(regexp_replace(
                split_part(coalesce(context->>'content_preview', ''), ' ', 1),
                '[^a-z0-9]', '', 'g'
            )) != ''
            ORDER BY count DESC
            LIMIT 20
        ", [
            $chatbot->id,
            $chatbot->organization_id,
            $from->toDateTimeString(),
            $to->toDateTimeString(),
        ]);

        $list = array_map(
            fn (object $row): array => ['topic' => (string) $row->topic, 'count' => (int) $row->count],
            $rows,
        );

        return $this->ok($list, $r);
    }

    // ── Unanswered questions ──────────────────────────────────────────────────

    public function unanswered(Request $r, Chatbot $chatbot): JsonResponse
    {
        $this->authorize('view', $chatbot);
        [$from, $to] = $this->parseRange($r);

        /** @var array<int, object> $rows */
        $rows = DB::select("
            SELECT
                ae_msg.context->>'content_preview' AS content_preview,
                COUNT(*)::integer                   AS count
            FROM analytics_events ae_msg
            WHERE ae_msg.event_type      = 'message_sent'
              AND ae_msg.chatbot_id      = ?
              AND ae_msg.organization_id = ?
              AND ae_msg.occurred_at BETWEEN ? AND ?
              AND (ae_msg.context->>'content_preview') IS NOT NULL
              AND length(ae_msg.context->>'content_preview') > 0
              AND ae_msg.conversation_id IN (
                  SELECT DISTINCT conversation_id
                  FROM analytics_events
                  WHERE event_type      = 'unanswered'
                    AND chatbot_id      = ?
                    AND organization_id = ?
                    AND occurred_at BETWEEN ? AND ?
                    AND conversation_id IS NOT NULL
              )
            GROUP BY ae_msg.context->>'content_preview'
            ORDER BY count DESC
            LIMIT 20
        ", [
            $chatbot->id, $chatbot->organization_id,
            $from->toDateTimeString(), $to->toDateTimeString(),
            $chatbot->id, $chatbot->organization_id,
            $from->toDateTimeString(), $to->toDateTimeString(),
        ]);

        $list = array_map(
            fn (object $row): array => [
                'content_preview' => (string) $row->content_preview,
                'count'           => (int) $row->count,
            ],
            $rows,
        );

        return $this->ok($list, $r);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array{Carbon, Carbon} */
    private function parseRange(Request $r): array
    {
        $range = $r->string('range')->toString() ?: '7d';

        if ($range === 'custom') {
            $rawFrom = $r->string('from')->toString();
            $rawTo   = $r->string('to')->toString();
            $from    = $rawFrom ? Carbon::parse($rawFrom)->startOfDay() : now()->subDays(7)->startOfDay();
            $to      = $rawTo   ? Carbon::parse($rawTo)->endOfDay()     : now()->endOfDay();
        } else {
            $days = match ($range) {
                '30d'  => 30,
                '90d'  => 90,
                default => 7,
            };
            $from = now()->subDays($days)->startOfDay();
            $to   = now()->endOfDay();
        }

        return [$from, $to];
    }
}
