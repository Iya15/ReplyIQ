<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Console\Command;

class FlushAnalyticsCommand extends Command
{
    protected $signature = 'analytics:flush';

    protected $description = 'Flush buffered analytics events from Redis into PostgreSQL';

    public function handle(AnalyticsRecorder $recorder): int
    {
        $count = $recorder->flush();

        if ($count > 0) {
            $this->info("Flushed {$count} analytics event(s).");
        }

        return self::SUCCESS;
    }
}
