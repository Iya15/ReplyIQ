'use client';

import { useState } from 'react';
import { MessageSquare, Search } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { useConversations } from '@/hooks/use-conversations';
import type { Conversation, ConversationStatus } from '@replyiq/api-client';

// ── Helpers ────────────────────────────────────────────────────────────────────

function visitorInitials(visitorId: string): string {
  const tail = visitorId.slice(-6);
  return tail.slice(0, 2).toUpperCase();
}

function visitorLabel(visitorId: string): string {
  return `visitor_${visitorId.slice(-6)}`;
}

function relativeTime(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime();
  const m = Math.floor(diff / 60_000);
  if (m < 1)  return 'just now';
  if (m < 60) return `${m}m`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h}h`;
  const d = Math.floor(h / 24);
  return `${d}d`;
}

// Deterministic color per visitor — cycles through 6 tailwind colors
const AVATAR_COLORS = [
  'bg-violet-500', 'bg-sky-500', 'bg-emerald-500',
  'bg-amber-500',  'bg-rose-500', 'bg-indigo-500',
];
function avatarColor(visitorId: string): string {
  let h = 0;
  for (let i = 0; i < visitorId.length; i++) h = (h * 31 + visitorId.charCodeAt(i)) >>> 0;
  return AVATAR_COLORS[h % AVATAR_COLORS.length]!;
}

// ── Sub-components ─────────────────────────────────────────────────────────────

const STATUS_TABS: Array<{ label: string; value: ConversationStatus | undefined }> = [
  { label: 'All',      value: undefined },
  { label: 'Active',   value: 'active' },
  { label: 'Resolved', value: 'resolved' },
];

function StatusBadge({ status }: { status: ConversationStatus }) {
  if (status === 'resolved') {
    return (
      <span className="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
        ✓ Resolved
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-medium bg-blue-500/10 text-blue-600 dark:text-blue-400">
      <span className="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse" />
      Active
    </span>
  );
}

function ConversationRow({
  conv,
  selected,
  onClick,
}: {
  conv: Conversation;
  selected: boolean;
  onClick: () => void;
}) {
  return (
    <button
      onClick={onClick}
      className={`w-full text-left px-4 py-3 border-b border-border last:border-0 transition-colors hover:bg-muted/50 ${
        selected ? 'bg-muted' : ''
      }`}
    >
      <div className="flex items-start gap-3">
        {/* Avatar */}
        <div
          className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white ${avatarColor(conv.visitor_id)}`}
        >
          {visitorInitials(conv.visitor_id)}
        </div>

        {/* Content */}
        <div className="flex-1 min-w-0">
          <div className="flex items-center justify-between gap-2 mb-0.5">
            <span className="text-xs font-medium truncate text-foreground">
              {visitorLabel(conv.visitor_id)}
            </span>
            <span className="text-[10px] text-muted-foreground shrink-0">
              {relativeTime(conv.created_at)}
            </span>
          </div>

          <p className="text-xs text-muted-foreground truncate leading-snug">
            {conv.last_message?.content ?? 'No messages yet'}
          </p>

          <div className="flex items-center justify-between mt-1.5">
            <StatusBadge status={conv.status} />
            {conv.message_count !== undefined && (
              <span className="text-[10px] text-muted-foreground">
                {conv.message_count} msg{conv.message_count !== 1 ? 's' : ''}
              </span>
            )}
          </div>
        </div>
      </div>
    </button>
  );
}

// ── Main component ─────────────────────────────────────────────────────────────

interface Props {
  chatbotId:    string;
  selectedId:   string | null;
  onSelect:     (id: string) => void;
}

export function ConversationList({ chatbotId, selectedId, onSelect }: Props) {
  const [statusFilter, setStatusFilter] = useState<ConversationStatus | undefined>(undefined);
  const [search, setSearch] = useState('');

  const { data, isLoading } = useConversations(chatbotId, {
    status: statusFilter,
    search: search || undefined,
  });

  const conversations = data?.data ?? [];
  const total = data?.meta.total ?? 0;

  return (
    <div className="flex flex-col h-full">
      {/* Search */}
      <div className="px-3 pt-3 pb-2">
        <div className="relative">
          <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search visitor ID…"
            className="pl-8 h-8 text-xs"
          />
        </div>
      </div>

      {/* Status tabs */}
      <div className="flex border-b px-1">
        {STATUS_TABS.map((tab) => (
          <button
            key={tab.label}
            onClick={() => setStatusFilter(tab.value)}
            className={`px-3 py-2 text-xs font-medium transition-colors border-b-2 -mb-px ${
              statusFilter === tab.value
                ? 'border-primary text-foreground'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            }`}
          >
            {tab.label}
            {tab.value === undefined && total > 0 && (
              <span className="ml-1 text-[10px] text-muted-foreground">({total})</span>
            )}
          </button>
        ))}
      </div>

      {/* List */}
      <div className="flex-1 overflow-y-auto">
        {isLoading ? (
          <div className="space-y-px p-3">
            {Array.from({ length: 5 }).map((_, i) => (
              <div key={i} className="flex items-start gap-3 p-2">
                <Skeleton className="h-8 w-8 rounded-full shrink-0" />
                <div className="flex-1 space-y-1.5">
                  <Skeleton className="h-3 w-24" />
                  <Skeleton className="h-3 w-40" />
                </div>
              </div>
            ))}
          </div>
        ) : conversations.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-full py-16 text-center px-6">
            <MessageSquare className="h-8 w-8 text-muted-foreground/30 mb-3" />
            <p className="text-sm font-medium">
              {search || statusFilter ? 'No conversations match' : 'No conversations yet'}
            </p>
            <p className="text-xs text-muted-foreground mt-1">
              {search || statusFilter
                ? 'Try adjusting your filter.'
                : 'Conversations will appear here once visitors start chatting.'}
            </p>
          </div>
        ) : (
          conversations.map((conv) => (
            <ConversationRow
              key={conv.id}
              conv={conv}
              selected={conv.id === selectedId}
              onClick={() => onSelect(conv.id)}
            />
          ))
        )}
      </div>
    </div>
  );
}
