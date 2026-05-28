<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // daily_conversation_counts — one row per (chatbot, date).
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW daily_conversation_counts AS
            SELECT
                chatbot_id,
                DATE(occurred_at)  AS date,
                COUNT(*)::integer  AS count
            FROM analytics_events
            WHERE event_type = 'conversation_started'
              AND chatbot_id IS NOT NULL
            GROUP BY chatbot_id, DATE(occurred_at)
            WITH NO DATA
        SQL);

        DB::statement(
            'CREATE UNIQUE INDEX daily_conversation_counts_pk
             ON daily_conversation_counts (chatbot_id, date)'
        );

        // weekly_topic_summary — first word of each message_sent event is the
        // "topic". Placeholder for LLM-based topic clustering (deferred).
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW weekly_topic_summary AS
            SELECT
                chatbot_id,
                DATE_TRUNC('week', occurred_at)::date AS week,
                lower(regexp_replace(
                    split_part(coalesce(context->>'content_preview', ''), ' ', 1),
                    '[^a-z0-9]', '', 'g'
                )) AS topic,
                COUNT(*)::integer AS count
            FROM analytics_events
            WHERE event_type  = 'message_sent'
              AND chatbot_id  IS NOT NULL
              AND (context->>'content_preview') IS NOT NULL
              AND length(coalesce(context->>'content_preview', '')) > 0
            GROUP BY chatbot_id,
                     DATE_TRUNC('week', occurred_at)::date,
                     lower(regexp_replace(
                         split_part(coalesce(context->>'content_preview', ''), ' ', 1),
                         '[^a-z0-9]', '', 'g'
                     ))
            WITH NO DATA
        SQL);

        DB::statement(
            'CREATE INDEX weekly_topic_summary_chatbot_week
             ON weekly_topic_summary (chatbot_id, week)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS weekly_topic_summary');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS daily_conversation_counts');
    }
};
