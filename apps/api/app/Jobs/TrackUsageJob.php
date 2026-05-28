<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\UsageRecord;
use App\Services\Billing\PlanLimits;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Nightly job that snapshots per-organization usage into the usage_records
 * table for billing dashboards and compliance auditing.
 *
 * Redis message counters are also checkpointed here into a DB-backed entry
 * so the data survives Redis eviction.
 */
class TrackUsageJob implements ShouldQueue
{
    use Queueable;

    private const METRICS = ['chatbots', 'documents', 'team_size', 'messages_per_month'];

    public int $timeout = 300;

    public function handle(PlanLimits $limits): void
    {
        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd = now()->endOfMonth()->toDateString();

        Organization::query()->withoutGlobalScopes()->each(function (Organization $org) use ($limits, $periodStart, $periodEnd): void {
            foreach (self::METRICS as $metric) {
                try {
                    $value = $limits->currentUsage($org, $metric);

                    UsageRecord::withoutGlobalScopes()->updateOrCreate(
                        [
                            'organization_id' => $org->id,
                            'metric' => $metric,
                            'period_start' => $periodStart,
                        ],
                        [
                            'value' => $value,
                            'period_end' => $periodEnd,
                            'recorded_at' => now(),
                        ],
                    );
                } catch (\Throwable $e) {
                    Log::error('TrackUsageJob: failed to record usage', [
                        'organization_id' => $org->id,
                        'metric' => $metric,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }
}
