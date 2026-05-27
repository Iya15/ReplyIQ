import { useEffect, useState } from 'react';

// ── Types ──────────────────────────────────────────────────────────────────────

interface ChatbotConfig {
  public_id:       string;
  name:            string;
  logo_url:        string | null;
  primary_color:   string | null;
  text_color:      string | null;
  welcome_message: string | null;
  position:        string | null;
  show_branding:   boolean;
}

interface Props {
  chatbotId: string;
}

// ── Component ─────────────────────────────────────────────────────────────────

export default function App({ chatbotId }: Props) {
  const [config, setConfig] = useState<ChatbotConfig | null>(null);
  const [error, setError]   = useState<string | null>(null);

  useEffect(() => {
    if (!chatbotId) {
      setError('No chatbot ID provided.');
      return;
    }

    fetch(`${__WIDGET_API_URL__}/public/chatbots/${encodeURIComponent(chatbotId)}/config`)
      .then((res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status.toString()}`);
        return res.json() as Promise<{ data: ChatbotConfig }>;
      })
      .then(({ data }) => {
        setConfig(data);

        // Apply chatbot branding as CSS custom properties.
        if (data.primary_color) {
          document.documentElement.style.setProperty('--color-primary', data.primary_color);
        }
        if (data.text_color) {
          document.documentElement.style.setProperty('--color-text', data.text_color);
        }
      })
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to load widget.');
      });
  }, [chatbotId]);

  // ── Loading ──────────────────────────────────────────────────────────────────

  if (error) {
    return (
      <div className="flex h-full items-center justify-center p-4 text-sm text-red-500">
        {error}
      </div>
    );
  }

  if (!config) {
    return (
      <div className="flex h-full items-center justify-center p-4">
        <div className="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
    );
  }

  // ── Placeholder UI (M3.4 replaces this with the real chat interface) ─────────

  return (
    <div className="flex h-full flex-col bg-white text-gray-900">
      <header className="flex items-center gap-3 border-b px-4 py-3">
        {config.logo_url && (
          <img
            src={config.logo_url}
            alt={config.name}
            className="h-8 w-8 rounded-full object-cover"
          />
        )}
        <p className="font-semibold">{config.name}</p>
      </header>

      <main className="flex flex-1 items-center justify-center p-6 text-center text-sm text-gray-500">
        <div className="space-y-2">
          <p className="font-medium text-gray-700">Widget for {config.name}</p>
          <p>Chat UI coming in M3.4</p>
        </div>
      </main>

      {config.show_branding && (
        <footer className="border-t px-4 py-2 text-center text-xs text-gray-400">
          Powered by{' '}
          <a
            href="https://replyiq.com"
            target="_blank"
            rel="noopener noreferrer"
            className="underline hover:text-gray-600"
          >
            ReplyIQ
          </a>
        </footer>
      )}
    </div>
  );
}
