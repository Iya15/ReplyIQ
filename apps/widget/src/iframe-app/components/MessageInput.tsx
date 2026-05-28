import { useCallback, useRef, useState } from 'react';
import type { ChatbotConfig } from '../lib/types';

interface Props {
  config:       ChatbotConfig;
  onSend:       (content: string) => void;
  disabled:     boolean;
  isEscalated?: boolean;
  onEscalate?:  () => void;
}

const MAX_CHARS     = 4000;
const WARN_AT_CHARS = 3600;
const LINE_HEIGHT   = 22;
const MAX_LINES     = 5;

export default function MessageInput({ config, onSend, disabled, isEscalated, onEscalate }: Props) {
  const [value, setValue]  = useState('');
  const textareaRef        = useRef<HTMLTextAreaElement>(null);
  const remaining          = MAX_CHARS - value.length;
  const showCounter        = value.length >= WARN_AT_CHARS;

  const resize = useCallback(() => {
    const ta = textareaRef.current;
    if (!ta) return;
    ta.style.height = 'auto';
    ta.style.height = `${Math.min(ta.scrollHeight, LINE_HEIGHT * MAX_LINES)}px`;
  }, []);

  function handleChange(e: React.ChangeEvent<HTMLTextAreaElement>) {
    if (e.target.value.length > MAX_CHARS) return;
    setValue(e.target.value);
    resize();
  }

  function submit() {
    const trimmed = value.trim();
    if (!trimmed || disabled) return;
    onSend(trimmed);
    setValue('');
    if (textareaRef.current) textareaRef.current.style.height = 'auto';
  }

  function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit(); }
  }

  return (
    <div className="border-t border-[var(--riq-border)] bg-[var(--riq-bg)]">
      <div className="flex items-end gap-2 px-3 py-2.5">
        <textarea
          ref={textareaRef}
          value={value}
          onChange={handleChange}
          onKeyDown={handleKeyDown}
          placeholder={isEscalated ? 'Message the agent…' : config.placeholder_text}
          disabled={disabled}
          rows={1}
          className="flex-1 resize-none rounded-xl border border-[var(--riq-border)]
                     bg-[var(--riq-surface)] px-3 py-2 text-sm text-[var(--riq-text)]
                     placeholder:text-[var(--riq-text)] placeholder:opacity-40
                     focus:outline-none focus:ring-2 focus:ring-[var(--riq-primary)]
                     focus:ring-offset-0 disabled:cursor-not-allowed disabled:opacity-50
                     leading-snug overflow-y-auto"
          style={{ lineHeight: `${LINE_HEIGHT}px`, maxHeight: `${LINE_HEIGHT * MAX_LINES}px` }}
          aria-label="Message input"
        />

        <button
          onClick={submit}
          disabled={disabled || !value.trim()}
          aria-label="Send message"
          className="flex-shrink-0 rounded-xl p-2 text-white transition-opacity
                     bg-[var(--riq-primary)] hover:opacity-90
                     disabled:cursor-not-allowed disabled:opacity-40"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
          </svg>
        </button>
      </div>

      {/* Counter + "Talk to a person" + branding */}
      <div className="flex items-center justify-between px-3 pb-2">
        <span
          className={`text-[10px] transition-opacity ${
            showCounter ? 'opacity-60' : 'opacity-0'
          } ${remaining < 200 ? 'text-red-500' : 'text-[var(--riq-text)]'}`}
        >
          {remaining} left
        </span>

        <div className="flex items-center gap-3">
          {!isEscalated && onEscalate && (
            <button
              onClick={onEscalate}
              className="text-[10px] text-[var(--riq-text)] opacity-40 hover:opacity-80 transition-opacity underline"
            >
              Talk to a person
            </button>
          )}
          {config.show_branding && (
            <a
              href="https://replyiq.com"
              target="_blank"
              rel="noopener noreferrer"
              className="text-[10px] text-[var(--riq-text)] opacity-30 hover:opacity-60 transition-opacity"
            >
              Powered by ReplyIQ
            </a>
          )}
        </div>
      </div>
    </div>
  );
}
