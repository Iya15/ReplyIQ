'use client';

import { use, useState } from 'react';
import { MessageSquare, Zap, Target, TrendingUp, MousePointerClick } from 'lucide-react';
import { KpiCard } from '@/components/analytics/kpi-card';
import { ConversationsChart } from '@/components/analytics/conversations-chart';
import { TopicsList } from '@/components/analytics/topics-list';
import { UnansweredList } from '@/components/analytics/unanswered-list';
import { ConversionFunnel } from '@/components/analytics/conversion-funnel';
import {
  useAnalyticsOverview,
  useAnalyticsConversations,
  useAnalyticsTopics,
  useAnalyticsUnanswered,
} from '@/hooks/use-analytics';
import type { AnalyticsRange } from '@replyiq/api-client';

interface PageProps {
  params: Promise<{ id: string }>;
}

const RANGES: { label: string; value: AnalyticsRange }[] = [
  { label: '7 days',  value: '7d' },
  { label: '30 days', value: '30d' },
  { label: '90 days', value: '90d' },
];

export default function AnalyticsPage({ params }: PageProps) {
  const { id }           = use(params);
  const [range, setRange] = useState<AnalyticsRange>('7d');

  const filters = { range };

  const overview      = useAnalyticsOverview(id, filters);
  const conversations = useAnalyticsConversations(id, filters);
  const topics        = useAnalyticsTopics(id, filters);
  const unanswered    = useAnalyticsUnanswered(id, filters);

  const kpis = overview.data?.data;

  const unansweredRate     = kpis?.unanswered_rate ?? 0;
  const unansweredPct      = `${Math.round(unansweredRate * 100)}%`;
  const unansweredHighlight = unansweredRate > 0.3 ? 'bad' : unansweredRate > 0.15 ? 'warn' : null;

  const avgConf      = kpis?.avg_confidence;
  const confPct      = avgConf !== null && avgConf !== undefined ? `${Math.round(avgConf * 100)}%` : null;
  const confHighlight = avgConf !== null && avgConf !== undefined
    ? (avgConf < 0.5 ? 'bad' : avgConf < 0.7 ? 'warn' : 'good')
    : null;

  return (
    <div className="p-6 space-y-8">
      {/* Header + range selector */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h2 className="text-lg font-semibold">Analytics</h2>
          <p className="text-sm text-muted-foreground mt-0.5">Performance overview for this chatbot</p>
        </div>

        <div className="flex items-center gap-1 rounded-lg border p-1 bg-muted/40">
          {RANGES.map((r) => (
            <button
              key={r.value}
              onClick={() => setRange(r.value)}
              className={`px-3 py-1.5 text-sm rounded-md font-medium transition-colors ${
                range === r.value
                  ? 'bg-background text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              {r.label}
            </button>
          ))}
        </div>
      </div>

      {/* KPI cards */}
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <KpiCard
          label="Conversations"
          value={kpis?.total_conversations.toLocaleString()}
          icon={<MessageSquare className="h-4 w-4" />}
          isLoading={overview.isLoading}
        />
        <KpiCard
          label="Messages"
          value={kpis?.total_messages.toLocaleString()}
          icon={<TrendingUp className="h-4 w-4" />}
          isLoading={overview.isLoading}
        />
        <KpiCard
          label="Avg Latency"
          value={kpis?.avg_latency_ms !== null && kpis?.avg_latency_ms !== undefined
            ? `${kpis.avg_latency_ms.toLocaleString()} ms`
            : null}
          sub="AI reply time"
          icon={<Zap className="h-4 w-4" />}
          isLoading={overview.isLoading}
        />
        <KpiCard
          label="Avg Confidence"
          value={confPct}
          highlight={confHighlight ?? null}
          sub="RAG retrieval score"
          icon={<Target className="h-4 w-4" />}
          isLoading={overview.isLoading}
        />
        <KpiCard
          label="Unanswered Rate"
          value={unansweredPct}
          highlight={unansweredHighlight}
          sub="Conversations with AI failure"
          icon={<MousePointerClick className="h-4 w-4" />}
          isLoading={overview.isLoading}
        />
      </div>

      {/* Chart + Funnel */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2 rounded-xl border bg-card p-5">
          <h3 className="text-sm font-semibold mb-4">Conversations per Day</h3>
          <ConversationsChart
            data={conversations.data?.data ?? []}
            isLoading={conversations.isLoading}
          />
        </div>

        <div className="rounded-xl border bg-card p-5">
          <h3 className="text-sm font-semibold mb-4">Conversion Funnel</h3>
          <ConversionFunnel
            data={kpis?.funnel}
            isLoading={overview.isLoading}
          />
        </div>
      </div>

      {/* Topics + Unanswered */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div className="rounded-xl border bg-card p-5">
          <h3 className="text-sm font-semibold mb-4">Top Topics</h3>
          <TopicsList
            data={topics.data?.data ?? []}
            isLoading={topics.isLoading}
          />
        </div>

        <div className="rounded-xl border bg-card p-5">
          <h3 className="text-sm font-semibold mb-1">Unanswered Questions</h3>
          <p className="text-xs text-muted-foreground mb-4">
            Bookmark questions to build a knowledge gaps backlog.
          </p>
          <UnansweredList
            data={unanswered.data?.data ?? []}
            chatbotId={id}
            isLoading={unanswered.isLoading}
          />
        </div>
      </div>
    </div>
  );
}
