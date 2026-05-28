import { useEffect, useRef } from 'react';
import MessageBubble from './MessageBubble';
import TypingIndicator from './TypingIndicator';
import type { ChatbotConfig, Message } from '../lib/types';

interface Props {
  config:     ChatbotConfig;
  messages:   Message[];
  isLoading:  boolean;
  onFeedback: (msgId: string, value: 'helpful' | 'not_helpful') => void;
}

function dayLabel(iso: string): string {
  const d    = new Date(iso);
  const now  = new Date();
  const diff = now.setHours(0, 0, 0, 0) - d.setHours(0, 0, 0, 0);
  if (diff === 0) return 'Today';
  if (diff === 86_400_000) return 'Yesterday';
  return new Date(iso).toLocaleDateString([], { month: 'short', day: 'numeric' });
}

// Insert day separators between messages on different calendar days.
type ListItem = Message | { type: 'separator'; label: string; key: string };

function buildItems(messages: Message[]): ListItem[] {
  const items: ListItem[] = [];
  let lastDay = '';

  for (const msg of messages) {
    const day = new Date(msg.created_at).toDateString();
    if (day !== lastDay) {
      items.push({ type: 'separator', label: dayLabel(msg.created_at), key: `sep-${day}` });
      lastDay = day;
    }
    items.push(msg);
  }
  return items;
}

const isPending = (msgs: Message[]) =>
  msgs.length > 0 && msgs[msgs.length - 1]?.role === 'assistant' &&
  msgs[msgs.length - 1]?.status === 'pending';

export default function MessageList({ config, messages, isLoading, onFeedback }: Props) {
  const bottomRef = useRef<HTMLDivElement>(null);

  // Auto-scroll to bottom whenever messages change.
  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const items = buildItems(messages);

  return (
    <div className="riq-scroll flex-1 overflow-y-auto px-4 py-4 flex flex-col gap-3">

      {/* Empty state: welcome message */}
      {messages.length === 0 && !isLoading && (
        <div className="riq-fade-in flex justify-start">
          <div
            className="max-w-[82%] rounded-[var(--riq-radius)] rounded-bl-[3px]
                       bg-[var(--riq-surface)] px-3.5 py-2.5 text-sm text-[var(--riq-text)]
                       leading-relaxed"
          >
            {config.welcome_message}
          </div>
        </div>
      )}

      {/* Loading skeleton */}
      {isLoading && (
        <div className="flex gap-2 items-end">
          <div className="h-7 w-40 animate-pulse rounded-xl bg-[var(--riq-surface)]" />
        </div>
      )}

      {/* Messages + day separators */}
      {items.map(item => {
        if ('type' in item) {
          return (
            <div key={item.key} className="flex items-center gap-3 my-1">
              <div className="flex-1 h-px bg-[var(--riq-border)]" />
              <span className="text-[10px] text-[var(--riq-text)] opacity-40 whitespace-nowrap">
                {item.label}
              </span>
              <div className="flex-1 h-px bg-[var(--riq-border)]" />
            </div>
          );
        }
        return (
          <MessageBubble key={item.id} message={item} onFeedback={onFeedback} />
        );
      })}

      {/* Typing indicator */}
      {isPending(messages) && <TypingIndicator />}

      <div ref={bottomRef} />
    </div>
  );
}
