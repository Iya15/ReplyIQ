import { useEffect, useRef, useState } from 'react';
import ChatWindow from './components/ChatWindow';
import { getSession, setSession } from './lib/storage';
import type { ChatbotConfig, Session } from './lib/types';

interface Props {
  chatbotId: string;
}

function applyTheme(config: ChatbotConfig): void {
  const r = document.documentElement.style;
  r.setProperty('--riq-primary', config.primary_color);
  r.setProperty('--riq-text',    config.text_color);
  r.setProperty('--riq-font',    config.font_family);

  if (config.theme === 'dark') {
    r.setProperty('--riq-bg',      '#0f172a');
    r.setProperty('--riq-surface', '#1e293b');
    r.setProperty('--riq-border',  '#334155');
  } else {
    r.setProperty('--riq-bg',      '#ffffff');
    r.setProperty('--riq-surface', '#f1f5f9');
    r.setProperty('--riq-border',  '#e2e8f0');
  }
}

export default function App({ chatbotId }: Props) {
  const [config,  setConfig]  = useState<ChatbotConfig | null>(null);
  const [session, setSessionState] = useState<Session | null>(null);
  const [error,   setError]   = useState<string | null>(null);
  const readySent = useRef(false);

  // 1. Fetch chatbot config + apply CSS vars
  useEffect(() => {
    if (!chatbotId) { setError('No chatbot ID.'); return; }

    fetch(`${__WIDGET_API_URL__}/public/chatbots/${encodeURIComponent(chatbotId)}/config`)
      .then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status.toString()}`);
        return res.json() as Promise<{ data: ChatbotConfig }>;
      })
      .then(({ data }) => {
        applyTheme(data);
        setConfig(data);
      })
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to load widget.');
      });
  }, [chatbotId]);

  // 2. Once config is ready: restore session from sessionStorage, then signal parent
  useEffect(() => {
    if (!config || readySent.current) return;
    readySent.current = true;

    const saved = getSession();
    if (saved) setSessionState(saved);

    window.parent.postMessage({ type: 'riq:ready' }, '*');
  }, [config]);

  // 3. Listen for riq:init from parent (new session handshake)
  useEffect(() => {
    function onMessage(ev: MessageEvent) {
      if (ev.data?.type !== 'riq:init') return;

      const s: Session = {
        token:          String(ev.data.token          ?? ''),
        conversationId: String(ev.data.conversationId ?? ''),
        visitorId:      String(ev.data.visitorId      ?? ''),
        apiBase:        String(ev.data.apiBase        ?? __WIDGET_API_URL__),
      };
      setSession(s);
      setSessionState(s);
    }

    window.addEventListener('message', onMessage);
    return () => window.removeEventListener('message', onMessage);
  }, []);

  // ── Error state ────────────────────────────────────────────────────────────
  if (error) {
    return (
      <div className="flex h-full flex-col items-center justify-center gap-3 p-6 text-center">
        <span className="text-sm text-red-500">{error}</span>
        <button
          onClick={() => { setError(null); window.location.reload(); }}
          className="rounded-lg px-3 py-1.5 text-xs font-medium
                     bg-[var(--riq-surface)] text-[var(--riq-text)]
                     hover:opacity-80 transition-opacity"
        >
          Retry
        </button>
      </div>
    );
  }

  // ── Loading skeleton ───────────────────────────────────────────────────────
  if (!config) {
    return (
      <div className="flex h-full flex-col overflow-hidden"
           style={{ background: 'var(--riq-bg)' }}>
        {/* Header skeleton */}
        <div className="flex items-center gap-3 border-b border-[var(--riq-border)] px-4 py-3">
          <div className="h-8 w-8 rounded-full animate-pulse bg-[var(--riq-surface)]" />
          <div className="flex-1 space-y-1.5">
            <div className="h-3 w-24 rounded animate-pulse bg-[var(--riq-surface)]" />
            <div className="h-2 w-12 rounded animate-pulse bg-[var(--riq-surface)]" />
          </div>
        </div>
        {/* Body skeleton */}
        <div className="flex flex-1 flex-col gap-3 px-4 py-4">
          <div className="h-8 w-48 rounded-xl animate-pulse bg-[var(--riq-surface)]" />
          <div className="h-8 w-36 self-end rounded-xl animate-pulse bg-[var(--riq-surface)]" />
          <div className="h-8 w-56 rounded-xl animate-pulse bg-[var(--riq-surface)]" />
        </div>
        {/* Input skeleton */}
        <div className="border-t border-[var(--riq-border)] px-3 py-2.5">
          <div className="h-9 rounded-xl animate-pulse bg-[var(--riq-surface)]" />
        </div>
      </div>
    );
  }

  // ── Ready ──────────────────────────────────────────────────────────────────
  return <ChatWindow config={config} session={session} />;
}
