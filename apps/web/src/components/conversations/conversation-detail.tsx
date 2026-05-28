'use client';

import { useState } from 'react';
import { CheckCircle, ExternalLink, Globe, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useConversation, useResolveConversation } from '@/hooks/use-conversations';
import { useMessages } from '@/hooks/use-messages';
import type { Message, MessageSource } from '@replyiq/api-client';

// ── Helpers ────────────────────────────────────────────────────────────────────

function visitorLabel(visitorId: string) {
  return `visitor_${visitorId.slice(-6)}`;
}

function formatTime(iso: string) {
  try {
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  } catch { return ''; }
}

function dayLabel(iso: string) {
  const d   = new Date(iso);
  const now = new Date();
  const diff = now.setHours(0,0,0,0) - new Date(d).setHours(0,0,0,0);
  if (diff === 0)           return 'Today';
  if (diff === 86_400_000)  return 'Yesterday';
  return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

// ── Bubble ─────────────────────────────────────────────────────────────────────

function MessageBubble({
  message,
  onSourceClick,
}: {
  message: Message;
  onSourceClick: (source: MessageSource) => void;
}) {
  const isUser = message.role === 'user';

  return (
    <div className={`flex ${isUser ? 'justify-end' : 'items-end gap-2'} group`}>
      <div className={`flex flex-col gap-1 max-w-[78%] ${isUser ? 'items-end' : 'items-start'}`}>
        <div
          className={`px-3.5 py-2.5 text-sm leading-relaxed rounded-2xl whitespace-pre-wrap break-words ${
            isUser
              ? 'bg-primary text-primary-foreground rounded-br-sm'
              : 'bg-muted text-foreground rounded-bl-sm'
          }`}
        >
          {message.content || (
            <span className="flex gap-1 items-center py-0.5">
              {[0,1,2].map(i => (
                <span key={i} className="h-1.5 w-1.5 rounded-full bg-current opacity-40 animate-bounce"
                  style={{ animationDelay: `${i * 0.15}s` }} />
              ))}
            </span>
          )}
        </div>

        {/* Sources row */}
        {!isUser && message.sources.length > 0 && (
          <div className="flex flex-wrap gap-1.5 px-1">
            {message.sources.map((src) => (
              <button
                key={src.id}
                onClick={() => onSourceClick(src)}
                className="inline-flex items-center gap-1 text-[10px] text-muted-foreground hover:text-foreground
                           bg-muted/60 hover:bg-muted rounded px-1.5 py-0.5 transition-colors"
              >
                <ExternalLink className="h-2.5 w-2.5" />
                {src.title}
              </button>
            ))}
          </div>
        )}

        {/* Timestamp */}
        <span className="px-1 text-[10px] text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity select-none">
          {formatTime(message.created_at)}
        </span>
      </div>
    </div>
  );
}

// ── Day separator ──────────────────────────────────────────────────────────────

function DaySeparator({ label }: { label: string }) {
  return (
    <div className="flex items-center gap-3 my-2">
      <div className="flex-1 h-px bg-border" />
      <span className="text-[10px] text-muted-foreground">{label}</span>
      <div className="flex-1 h-px bg-border" />
    </div>
  );
}

// ── Source side-panel ──────────────────────────────────────────────────────────

function SourcePanel({
  source,
  onClose,
}: {
  source: MessageSource | null;
  onClose: () => void;
}) {
  return (
    <Sheet open={!!source} onOpenChange={(open) => !open && onClose()}>
      <SheetContent side="right" className="w-[400px] sm:w-[480px]">
        {source && (
          <>
            <SheetHeader className="pr-8">
              <SheetTitle className="text-base truncate">{source.title}</SheetTitle>
            </SheetHeader>
            <div className="mt-4 space-y-4">
              {source.url && (
                <a
                  href={source.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1.5 text-xs text-primary hover:underline"
                >
                  <Globe className="h-3 w-3" />
                  {source.url}
                  <ExternalLink className="h-3 w-3" />
                </a>
              )}
              <p className="text-xs text-muted-foreground">
                This source was cited by the assistant as relevant to the response.
              </p>
            </div>
          </>
        )}
      </SheetContent>
    </Sheet>
  );
}

// ── Loading skeleton ───────────────────────────────────────────────────────────

function DetailSkeleton() {
  return (
    <div className="flex flex-col h-full">
      <div className="border-b px-4 py-3 space-y-2">
        <Skeleton className="h-4 w-32" />
        <Skeleton className="h-3 w-48" />
      </div>
      <div className="flex-1 p-4 space-y-3">
        {Array.from({ length: 4 }).map((_, i) => (
          <div key={i} className={`flex ${i % 2 === 0 ? '' : 'justify-end'}`}>
            <Skeleton className={`h-10 rounded-2xl ${i % 2 === 0 ? 'w-56' : 'w-40'}`} />
          </div>
        ))}
      </div>
    </div>
  );
}

// ── Empty state ────────────────────────────────────────────────────────────────

export function ConversationDetailEmpty() {
  return (
    <div className="flex h-full flex-col items-center justify-center text-center p-8">
      <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted mb-4">
        <ExternalLink className="h-5 w-5 text-muted-foreground" />
      </div>
      <p className="text-sm font-medium">Select a conversation</p>
      <p className="text-xs text-muted-foreground mt-1">
        Choose a conversation from the list to view details.
      </p>
    </div>
  );
}

// ── Main component ─────────────────────────────────────────────────────────────

interface Props {
  conversationId: string;
  chatbotId:      string;
}

export function ConversationDetail({ conversationId, chatbotId }: Props) {
  const [activeSource, setActiveSource] = useState<MessageSource | null>(null);

  const { data: convRes, isLoading: convLoading } = useConversation(conversationId);
  const { data: messages, isLoading: msgsLoading } = useMessages(conversationId);
  const { mutate: resolve, isPending: resolving } = useResolveConversation(chatbotId);

  const conversation = convRes?.data;
  const isLoading    = convLoading || msgsLoading;

  if (isLoading) return <DetailSkeleton />;
  if (!conversation) return null;

  // Build message list with day separators
  const items: Array<Message | { type: 'separator'; label: string; key: string }> = [];
  let lastDay = '';
  for (const msg of messages ?? []) {
    const day = new Date(msg.created_at).toDateString();
    if (day !== lastDay) {
      items.push({ type: 'separator', label: dayLabel(msg.created_at), key: `sep-${day}` });
      lastDay = day;
    }
    items.push(msg);
  }

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="border-b px-4 py-3 flex items-start justify-between gap-4 shrink-0">
        <div className="min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <span className="text-sm font-semibold truncate">
              {visitorLabel(conversation.visitor_id)}
            </span>
            {conversation.status === 'resolved' ? (
              <span className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                <CheckCircle className="h-3 w-3" /> Resolved
              </span>
            ) : (
              <span className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-blue-500/10 text-blue-600 dark:text-blue-400">
                <span className="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse" /> Active
              </span>
            )}
          </div>
          <div className="flex items-center gap-3 mt-0.5 text-xs text-muted-foreground flex-wrap">
            <span>Started {new Date(conversation.created_at).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
            {conversation.source_url && (
              <a
                href={conversation.source_url}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1 hover:text-foreground transition-colors truncate max-w-[200px]"
              >
                <Globe className="h-3 w-3 shrink-0" />
                <span className="truncate">{conversation.source_url.replace(/^https?:\/\//, '')}</span>
                <ExternalLink className="h-2.5 w-2.5 shrink-0" />
              </a>
            )}
            {conversation.country && (
              <span>{conversation.country}</span>
            )}
          </div>
        </div>

        {conversation.status === 'active' && (
          <Button
            size="sm"
            variant="outline"
            onClick={() => resolve(conversationId)}
            disabled={resolving}
            className="shrink-0"
          >
            <CheckCircle className="h-3.5 w-3.5 mr-1.5" />
            Mark resolved
          </Button>
        )}
      </div>

      {/* Messages */}
      <div className="flex-1 overflow-y-auto px-4 py-4 space-y-3">
        {items.length === 0 && (
          <p className="text-center text-xs text-muted-foreground py-8">No messages yet.</p>
        )}
        {items.map((item) => {
          if ('type' in item) {
            return <DaySeparator key={item.key} label={item.label} />;
          }
          return (
            <MessageBubble
              key={item.id}
              message={item}
              onSourceClick={setActiveSource}
            />
          );
        })}
      </div>

      {/* Source side-panel */}
      <SourcePanel source={activeSource} onClose={() => setActiveSource(null)} />
    </div>
  );
}
