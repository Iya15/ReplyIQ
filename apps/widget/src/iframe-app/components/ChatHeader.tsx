import type { ChatbotConfig } from '../lib/types';

interface Props {
  config:   ChatbotConfig;
  onClose:  () => void;
}

export default function ChatHeader({ config, onClose }: Props) {
  const imgSrc = config.logo_url ?? config.avatar_url;

  return (
    <header
      className="flex items-center gap-3 border-b border-[var(--riq-border)]
                 bg-[var(--riq-bg)] px-4 py-3 flex-shrink-0"
    >
      {/* Avatar / logo */}
      {imgSrc ? (
        <img
          src={imgSrc}
          alt={config.name}
          className="h-8 w-8 rounded-full object-cover flex-shrink-0"
        />
      ) : (
        <div
          className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full
                     text-sm font-semibold text-white"
          style={{ background: 'var(--riq-primary)' }}
          aria-hidden="true"
        >
          {config.name.charAt(0).toUpperCase()}
        </div>
      )}

      {/* Name + status */}
      <div className="flex-1 min-w-0">
        <p className="truncate text-sm font-semibold text-[var(--riq-text)]">{config.name}</p>
        <div className="flex items-center gap-1.5 mt-0.5">
          <span className="h-1.5 w-1.5 rounded-full bg-green-500" aria-hidden="true" />
          <span className="text-[11px] text-[var(--riq-text)] opacity-50">Online</span>
        </div>
      </div>

      {/* Close button */}
      <button
        onClick={onClose}
        aria-label="Close chat"
        className="rounded-lg p-1.5 text-[var(--riq-text)] opacity-40
                   hover:opacity-80 hover:bg-[var(--riq-surface)] transition-all flex-shrink-0"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             strokeWidth="2.5" strokeLinecap="round" aria-hidden="true">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </button>
    </header>
  );
}
