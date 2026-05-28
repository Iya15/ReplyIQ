<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshAnalyticsViewsCommand extends Command
{
    protected $signature   = 'analytics:views:refresh';
    protected $description = 'Refresh analytics materialized views (daily_conversation_counts, weekly_topic_summary)';

    public function handle(): int
    {
        // CONCURRENTLY allows reads during refresh; requires a unique index on each view.
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY daily_conversation_counts');
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY weekly_topic_summary');

        $this->info('Analytics views refreshed.');

        return self::SUCCESS;
    }
}
