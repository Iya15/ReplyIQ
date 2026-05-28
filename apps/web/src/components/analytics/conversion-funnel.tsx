'use client';

import { Skeleton } from '@/components/ui/skeleton';
import type { AnalyticsFunnel } from '@replyiq/api-client';

interface ConversionFunnelProps {
  data?:      AnalyticsFunnel;
  isLoading?: boolean;
}

const STEPS = [
  { key: 'widget_opens',          label: 'Widget Opens',           color: 'bg-blue-500' },
  { key: 'conversations_started', label: 'Conversations Started',  color: 'bg-indigo-500' },
  { key: 'messages_sent',         label: 'Messages Sent',          color: 'bg-violet-500' },
  { key: 'resolved',              label: 'Resolved',               color: 'bg-green-500' },
] as const;

export function ConversionFunnel({ data, isLoading }: ConversionFunnelProps) {
  if (isLoading) {
    return (
      <div className="space-y-3">
        {Array.from({ length: 4 }).map((_, i) => (
          <Skeleton key={i} className="h-10 rounded-lg" />
        ))}
      </div>
    );
  }

  if (!data) return null;

  const top = Math.max(data.widget_opens, data.conversations_started, data.messages_sent, data.resolved, 1);

  return (
    <div className="space-y-3">
      {STEPS.map(({ key, label, color }) => {
        const value = data[key];
        const pct   = Math.round((value / top) * 100);

        return (
          <div key={key}>
            <div className="flex justify-between text-sm mb-1">
              <span className="text-muted-foreground">{label}</span>
              <span className="font-medium tabular-nums">{value.toLocaleString()}</span>
            </div>
            <div className="h-2.5 bg-muted rounded-full overflow-hidden">
              <div className={`h-full ${color} rounded-full transition-all`} style={{ width: `${pct}%` }} />
            </div>
          </div>
        );
      })}
    </div>
  );
}
