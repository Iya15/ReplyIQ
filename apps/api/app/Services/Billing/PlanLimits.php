<?php

namespace App\Services\Billing;

use App\Billing\Plans;
use App\Models\Document;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Support\Facades\Redis;

class PlanLimits
{
    /**
     * Returns true when the organization is within the limit for the given metric.
     * Always returns true for unlimited (-1) or unknown metrics.
     */
    public function check(Organization $org, string $metric): bool
    {
        $limit = Plans::get($org->plan)[$metric] ?? null;

        if (! is_int($limit) || $limit === -1) {
            return true;
        }

        return $this->currentUsage($org, $metric) < $limit;
    }

    /**
     * Returns the current usage count for the given metric.
     */
    public function currentUsage(Organization $org, string $metric): int
    {
        return match ($metric) {
            'chatbots' => $org->chatbots()->count(),
            'documents' => Document::withoutGlobalScopes()
                ->where('organization_id', $org->id)
                ->count(),
            'team_size' => Membership::where('organization_id', $org->id)->count(),
            'messages_per_month' => $this->monthlyMessages($org),
            default => 0,
        };
    }

    /**
     * Returns how many more units can be created before hitting the limit.
     * Returns PHP_INT_MAX for unlimited plans.
     */
    public function remaining(Organization $org, string $metric): int
    {
        $limit = Plans::get($org->plan)[$metric] ?? null;

        if (! is_int($limit) || $limit === -1) {
            return PHP_INT_MAX;
        }

        return max(0, $limit - $this->currentUsage($org, $metric));
    }

    /**
     * Read the current month's message count from Redis.
     * Redis key: usage:messages:{org_id}:{YYYY-MM}
     */
    private function monthlyMessages(Organization $org): int
    {
        try {
            $key = "usage:messages:{$org->id}:".now()->format('Y-m');

            return (int) (Redis::get($key) ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
