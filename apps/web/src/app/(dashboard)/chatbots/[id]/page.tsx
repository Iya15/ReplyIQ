'use client';

import { use } from 'react';
import { MessageSquare, Users, TrendingUp, Clock } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useChatbot } from '@/hooks/use-chatbots';

const STAT_CARDS = [
  { label: 'Total Conversations', icon: MessageSquare, value: '—' },
  { label: 'Unique Visitors', icon: Users, value: '—' },
  { label: 'Resolution Rate', icon: TrendingUp, value: '—' },
  { label: 'Avg. Response Time', icon: Clock, value: '—' },
];

interface PageProps {
  params: Promise<{ id: string }>;
}

export default function ChatbotOverviewPage({ params }: PageProps) {
  const { id } = use(params);
  const { data: chatbot, isLoading } = useChatbot(id);

  return (
    <div className="p-6 space-y-6">
      {/* Status badge */}
      {!isLoading && chatbot && (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <span>Status:</span>
          <span
            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${
              chatbot.status === 'active'
                ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                : chatbot.status === 'paused'
                ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                : 'bg-zinc-500/15 text-zinc-600 dark:text-zinc-400'
            }`}
          >
            {chatbot.status}
          </span>
        </div>
      )}

      {/* Stats */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {STAT_CARDS.map((stat) => (
          <Card key={stat.label}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                {stat.label}
              </CardTitle>
              <stat.icon className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-bold">{stat.value}</p>
              <p className="text-xs text-muted-foreground mt-1">Analytics coming soon</p>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Conversations table placeholder */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Recent Conversations</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 4 }).map((_, i) => (
                <Skeleton key={i} className="h-10 w-full" />
              ))}
            </div>
          ) : (
            <div className="flex flex-col items-center justify-center py-12 text-center">
              <MessageSquare className="h-8 w-8 text-muted-foreground/40 mb-3" />
              <p className="text-sm text-muted-foreground">No conversations yet.</p>
              <p className="text-xs text-muted-foreground mt-1">
                Embed your chatbot to start receiving messages.
              </p>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
