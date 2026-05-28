<?php

namespace App\Jobs;

use App\Models\AnalyticsEvent;
use App\Models\Organization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Fetches the previous day's OpenAI API usage and logs a structured report.
 *
 * Scheduled daily at 06:00 UTC (see bootstrap/app.php).
 *
 * Usage data is logged at INFO level — ingested by Logtail for dashboarding
 * and optionally pushed to Slack if LOG_OPENAI_REPORT_WEBHOOK is set.
 *
 * OpenAI API: GET https://api.openai.com/v1/usage?date=YYYY-MM-DD
 * Docs: https://platform.openai.com/docs/api-reference/usage
 */
class DailyOpenAiCostReportJob implements ShouldQueue
{
    use QueueableTrait;

    public function __construct()
    {
        $this->onQueue('default');
    }

    // ── Token cost per million (USD) — update when OpenAI changes pricing ────
    private const COSTS_PER_MILLION = [
        'gpt-4o-mini' => ['input' => 0.15,  'output' => 0.60],
        'gpt-4o' => ['input' => 2.50,  'output' => 10.00],
        'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
        'text-embedding-3-small' => ['input' => 0.02,  'output' => 0.00],
        'text-embedding-3-large' => ['input' => 0.13,  'output' => 0.00],
    ];

    public function handle(): void
    {
        $apiKey = config('services.openai.api_key') ?? env('OPENAI_API_KEY');

        if (! $apiKey) {
            Log::warning('DailyOpenAiCostReportJob: OPENAI_API_KEY not configured, skipping.');

            return;
        }

        $yesterday = now()->subDay()->format('Y-m-d');

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->get('https://api.openai.com/v1/usage', ['date' => $yesterday]);

        if (! $response->ok()) {
            Log::warning('DailyOpenAiCostReportJob: usage API returned '.$response->status(), [
                'body' => $response->body(),
            ]);

            return;
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        /** @var array<int, array<string, mixed>> $data */
        $data = (array) ($body['data'] ?? []);

        // Aggregate by model
        $byModel = [];
        $totalCost = 0.0;

        foreach ($data as $entry) {
            /** @var array<string, mixed> $entry */
            $model = (string) ($entry['model'] ?? 'unknown');
            $input = (int) ($entry['n_context_tokens_total'] ?? 0);
            $output = (int) ($entry['n_generated_tokens_total'] ?? 0);
            $costs = self::COSTS_PER_MILLION[$model] ?? ['input' => 0, 'output' => 0];
            $cost = ($input / 1_000_000) * $costs['input'] + ($output / 1_000_000) * $costs['output'];

            $byModel[$model] ??= ['input_tokens' => 0, 'output_tokens' => 0, 'cost_usd' => 0.0];
            $byModel[$model]['input_tokens'] += $input;
            $byModel[$model]['output_tokens'] += $output;
            $byModel[$model]['cost_usd'] += $cost;
            $totalCost += $cost;
        }

        $totalInputTokens = array_sum(array_column($byModel, 'input_tokens'));
        $totalOutputTokens = array_sum(array_column($byModel, 'output_tokens'));

        Log::info('OpenAI daily usage report', [
            'date' => $yesterday,
            'total_cost_usd' => round($totalCost, 4),
            'total_input_tokens' => $totalInputTokens,
            'total_output_tokens' => $totalOutputTokens,
            'by_model' => $byModel,
        ]);

        // ── Per-organization usage (via analytics_events context) ────────────
        $this->logPerOrganizationUsage($yesterday);

        // ── Optional Slack notification ───────────────────────────────────────
        $this->notifySlack($yesterday, $totalCost, $totalInputTokens, $totalOutputTokens);
    }

    private function logPerOrganizationUsage(string $date): void
    {
        // Count messages_replied events per organization for the given date.
        // This is an approximation of per-org LLM cost (exact costs require
        // per-request token tracking, which is stored in messages.tokens_used).
        $start = Carbon::parse($date)->startOfDay();
        $end = Carbon::parse($date)->endOfDay();

        $perOrg = AnalyticsEvent::withoutGlobalScopes()
            ->where('event_type', 'message_replied')
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('organization_id, COUNT(*) as reply_count, AVG((context->>\'tokens_used\')::float) as avg_tokens')
            ->groupBy('organization_id')
            ->get();

        foreach ($perOrg as $row) {
            /** @var array<string, mixed> $raw */
            $raw = $row->toArray();
            $orgId = (string) ($raw['organization_id'] ?? '');
            $replyCount = (int) ($raw['reply_count'] ?? 0);
            $avgTokens = (float) ($raw['avg_tokens'] ?? 0.0);

            $org = Organization::withoutGlobalScopes()->find($orgId);
            Log::info('OpenAI per-org usage', [
                'date' => $date,
                'organization' => $org?->name ?? $orgId,
                'plan' => $org?->plan ?? 'unknown',
                'reply_count' => $replyCount,
                'avg_tokens' => round($avgTokens, 1),
                'est_tokens' => $replyCount * (int) round($avgTokens),
            ]);
        }
    }

    private function notifySlack(
        string $date,
        float $totalCost,
        int $inputTokens,
        int $outputTokens,
    ): void {
        $webhookUrl = config('services.slack.cost_webhook_url');
        if (! $webhookUrl) {
            return;
        }

        Http::post((string) $webhookUrl, [
            'text' => sprintf(
                ':bar_chart: *OpenAI usage — %s*'."\n".
                'Total cost: *$%s*'."\n".
                'Tokens: %s in / %s out',
                $date,
                number_format($totalCost, 4),
                number_format($inputTokens),
                number_format($outputTokens),
            ),
        ]);
    }
}
