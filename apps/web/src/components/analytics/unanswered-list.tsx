'use client';

import { useState, useCallback } from 'react';
import { BookmarkPlus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { UnansweredQuestion } from '@replyiq/api-client';

interface UnansweredListProps {
  data:       UnansweredQuestion[];
  chatbotId:  string;
  isLoading?: boolean;
}

const GAPS_KEY = (chatbotId: string) => `replyiq:gaps:${chatbotId}`;

function loadGaps(chatbotId: string): string[] {
  try {
    const raw = localStorage.getItem(GAPS_KEY(chatbotId));
    return raw ? (JSON.parse(raw) as string[]) : [];
  } catch {
    return [];
  }
}

function saveGaps(chatbotId: string, gaps: string[]): void {
  localStorage.setItem(GAPS_KEY(chatbotId), JSON.stringify(gaps));
}

export function UnansweredList({ data, chatbotId, isLoading }: UnansweredListProps) {
  const [gaps, setGaps] = useState<string[]>(() => loadGaps(chatbotId));

  const addToGaps = useCallback((question: string) => {
    setGaps((prev) => {
      if (prev.includes(question)) return prev;
      const next = [...prev, question];
      saveGaps(chatbotId, next);
      return next;
    });
  }, [chatbotId]);

  const removeGap = useCallback((question: string) => {
    setGaps((prev) => {
      const next = prev.filter((q) => q !== question);
      saveGaps(chatbotId, next);
      return next;
    });
  }, [chatbotId]);

  if (isLoading) {
    return (
      <div className="space-y-2">
        {Array.from({ length: 5 }).map((_, i) => (
          <Skeleton key={i} className="h-10 w-full rounded-lg" />
        ))}
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Unanswered questions */}
      {data.length === 0 ? (
        <p className="text-sm text-muted-foreground py-6 text-center">
          No unanswered questions in this period.
        </p>
      ) : (
        <ul className="space-y-1.5">
          {data.map(({ content_preview, count }) => {
            const inGaps = gaps.includes(content_preview);
            return (
              <li
                key={content_preview}
                className="flex items-center gap-3 px-3 py-2.5 rounded-lg border bg-card hover:bg-muted/50 transition-colors"
              >
                <span className="flex-1 text-sm truncate" title={content_preview}>
                  {content_preview}
                </span>
                <span className="text-xs text-muted-foreground tabular-nums shrink-0">
                  ×{count}
                </span>
                <Button
                  size="icon"
                  variant={inGaps ? 'secondary' : 'ghost'}
                  className="h-7 w-7 shrink-0"
                  onClick={() => inGaps ? removeGap(content_preview) : addToGaps(content_preview)}
                  title={inGaps ? 'Remove from knowledge gaps' : 'Add to knowledge gaps'}
                >
                  {inGaps ? (
                    <X className="h-3.5 w-3.5" />
                  ) : (
                    <BookmarkPlus className="h-3.5 w-3.5" />
                  )}
                </Button>
              </li>
            );
          })}
        </ul>
      )}

      {/* Knowledge gaps backlog */}
      {gaps.length > 0 && (
        <div className="rounded-xl border border-amber-200 bg-amber-50/50 dark:border-amber-900 dark:bg-amber-950/20 p-4">
          <h4 className="text-sm font-semibold text-amber-900 dark:text-amber-300 mb-3 flex items-center gap-2">
            <BookmarkPlus className="h-4 w-4" />
            Knowledge Gaps Backlog
            <span className="ml-auto text-xs font-normal text-amber-700 dark:text-amber-400">
              {gaps.length} item{gaps.length !== 1 ? 's' : ''}
            </span>
          </h4>
          <ul className="space-y-1.5">
            {gaps.map((q) => (
              <li key={q} className="flex items-center gap-2 text-sm">
                <span className="flex-1 truncate text-amber-900 dark:text-amber-200" title={q}>{q}</span>
                <button
                  onClick={() => removeGap(q)}
                  className="text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-200 shrink-0"
                  title="Remove"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
