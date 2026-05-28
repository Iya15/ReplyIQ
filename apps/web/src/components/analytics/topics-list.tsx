'use client';

import { Skeleton } from '@/components/ui/skeleton';
import type { TopicCount } from '@replyiq/api-client';

interface TopicsListProps {
  data:       TopicCount[];
  isLoading?: boolean;
}

export function TopicsList({ data, isLoading }: TopicsListProps) {
  if (isLoading) {
    return (
      <div className="space-y-2">
        {Array.from({ length: 6 }).map((_, i) => (
          <Skeleton key={i} className="h-7 w-full rounded" />
        ))}
      </div>
    );
  }

  if (data.length === 0) {
    return (
      <p className="text-sm text-muted-foreground py-6 text-center">
        No topics found for this period.
      </p>
    );
  }

  const max = data[0]?.count ?? 1;

  return (
    <ol className="space-y-2">
      {data.map(({ topic, count }, i) => (
        <li key={topic} className="flex items-center gap-3">
          <span className="w-5 text-xs text-muted-foreground text-right shrink-0">{i + 1}</span>
          <div className="flex-1 min-w-0">
            <div className="flex items-center justify-between mb-0.5">
              <span className="text-sm font-medium capitalize truncate">{topic}</span>
              <span className="text-xs text-muted-foreground ml-2 shrink-0">{count}</span>
            </div>
            <div className="h-1.5 bg-muted rounded-full overflow-hidden">
              <div
                className="h-full bg-primary rounded-full"
                style={{ width: `${Math.round((count / max) * 100)}%` }}
              />
            </div>
          </div>
        </li>
      ))}
    </ol>
  );
}
