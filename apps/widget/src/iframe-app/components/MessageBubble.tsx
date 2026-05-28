import { useState, useMemo } from 'react';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import type { Message } from '../lib/types';

marked.use({ gfm: true, breaks: false });

interface Props {
  message:      Message;
  onFeedback:   (msgId: string, value: 'helpful' | 'not_helpful') => void;
}

function formatTime(iso: string): string {
  try {
    const d = new Date(iso);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  } catch {
    return '';
  }
}

export default function MessageBubble({ message, onFeedback }: Props) {
  const [feedback, setFeedback] = useState<'helpful' | 'not_helpful' | null>(null);
  const isUser = message.role === 'user';

  const html = useMemo(() => {
    if (isUser) return '';
    return DOMPurify.sanitize(marked.parse(message.content) as string);
  }, [isUser, message.content]);

  function handleFeedback(value: 'helpful' | 'not_helpful') {
    if (feedback) return;
    setFeedback(value);
    onFeedback(message.id, value);
  }

  return (
    <div className={`group flex riq-msg-enter ${isUser ? 'justify-end' : 'items-end gap-2'}`}>
      <div className={`flex flex-col gap-1 max-w-[80%] ${isUser ? 'items-end' : 'items-start'}`}>
        {/* Bubble */}
        <div
          className={`
            relative px-3.5 py-2.5 text-sm leading-relaxed
            ${isUser
              ? 'bg-[var(--riq-primary)] text-white rounded-[var(--riq-radius)] rounded-br-[3px]'
              : 'bg-[var(--riq-surface)] text-[var(--riq-text)] rounded-[var(--riq-radius)] rounded-bl-[3px]'}
          `}
        >
          {isUser ? (
            <span className="whitespace-pre-wrap break-words">{message.content}</span>
          ) : (
            // eslint-disable-next-line react/no-danger
            <div className="riq-prose" dangerouslySetInnerHTML={{ __html: html }} />
          )}
        </div>

        {/* Sources + feedback row (assistant only) */}
        {!isUser && message.status === 'complete' && (
          <div className="flex items-center gap-2 px-1">
            {message.sources.length > 0 && (
              <span className="text-xs text-[var(--riq-text)] opacity-50">
                {message.sources.length} source{message.sources.length !== 1 ? 's' : ''}
              </span>
            )}

            <div className="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
              <button
                onClick={() => handleFeedback('helpful')}
                aria-label="Helpful"
                className={`rounded p-0.5 text-base leading-none transition-colors
                  ${feedback === 'helpful'
                    ? 'text-green-500'
                    : 'text-[var(--riq-text)] opacity-40 hover:opacity-80'}`}
              >
                👍
              </button>
              <button
                onClick={() => handleFeedback('not_helpful')}
                aria-label="Not helpful"
                className={`rounded p-0.5 text-base leading-none transition-colors
                  ${feedback === 'not_helpful'
                    ? 'text-red-400'
                    : 'text-[var(--riq-text)] opacity-40 hover:opacity-80'}`}
              >
                👎
              </button>
            </div>
          </div>
        )}

        {/* Timestamp on hover */}
        <span
          className="px-1 text-[10px] text-[var(--riq-text)] opacity-0 group-hover:opacity-40
                     transition-opacity select-none"
        >
          {formatTime(message.created_at)}
        </span>
      </div>
    </div>
  );
}
