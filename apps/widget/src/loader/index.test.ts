import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { createLoader } from './index';

// ── Fixtures ───────────────────────────────────────────────────────────────────

const CHATBOT_ID    = 'pub-test-123';
const WIDGET_ORIGIN = 'https://widget.replyiq.com';

const fakeConfig = {
  name:            'Test Bot',
  primary_color:   '#4F46E5',
  position:        'bottom-right' as const,
  welcome_message: 'Hello!',
  avatar_url:      null,
};

const fakeConvResponse = {
  session_token: 'tok.abc.def',
  data:          { id: 'conv-uuid-1', status: 'active' },
};

function mockFetch(data: unknown, ok = true): ReturnType<typeof vi.fn> {
  return vi.fn().mockResolvedValue({
    ok,
    json: () => Promise.resolve(data),
  });
}

afterEach(() => {
  document.body.innerHTML = '';
  vi.restoreAllMocks();
});

// ── 1. Queue drain ─────────────────────────────────────────────────────────────

describe('queue drain', () => {
  it('processes commands queued in window.riq.q before the loader loaded', async () => {
    vi.stubGlobal('fetch', mockFetch({ data: fakeConfig }));

    // Simulate the embed snippet having queued an init call before widget.js loaded.
    const stub = function () {} as Window['riq'];
    (stub as NonNullable<Window['riq']>).q = [
      Object.assign(['init', { chatbotId: CHATBOT_ID }], { length: 2 }) as unknown as IArguments,
    ];
    vi.stubGlobal('riq', stub);

    const loader = createLoader();
    const prior  = window.riq;
    if (prior && typeof prior === 'function' && Array.isArray(prior.q)) {
      for (const args of prior.q) {
        if (typeof args[0] === 'string') loader.processCommand(args[0] as string, args[1] as unknown);
      }
    }
    window.riq = (cmd: string, opts?: unknown) => loader.processCommand(cmd, opts);

    await vi.waitFor(() => expect(loader.getState().chatbotId).toBe(CHATBOT_ID));
  });
});

// ── 2. createLauncher attrs ────────────────────────────────────────────────────

describe('createLauncher', () => {
  let loader: ReturnType<typeof createLoader>;

  beforeEach(async () => {
    vi.stubGlobal('fetch', mockFetch({ data: fakeConfig }));
    loader = createLoader();
    loader.processCommand('init', { chatbotId: CHATBOT_ID });
    // Wait until init completes and the launcher is in the DOM.
    await vi.waitFor(() => expect(loader.getState().launcher).not.toBeNull());
  });

  it('creates a launcher button with correct accessibility attributes', () => {
    const btn = loader.getState().launcher as HTMLElement;
    expect(btn.tagName).toBe('BUTTON');
    expect(btn.getAttribute('aria-label')).toBe(`Chat with ${fakeConfig.name}`);
    expect(btn.getAttribute('data-riq')).toBe('launcher');
    expect(btn.getAttribute('tabindex')).toBe('0');
  });
});

// ── 3. openWidget iframe ───────────────────────────────────────────────────────

describe('openWidget', () => {
  let loader: ReturnType<typeof createLoader>;

  beforeEach(async () => {
    vi.stubGlobal('fetch', vi.fn()
      .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve({ data: fakeConfig }) })
      .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve(fakeConvResponse) }),
    );
    loader = createLoader();
    loader.processCommand('init', { chatbotId: CHATBOT_ID });
    await vi.waitFor(() => expect(loader.getState().launcher).not.toBeNull());
  });

  it('injects an iframe when the widget is opened', async () => {
    loader.processCommand('open');
    await vi.waitFor(() => expect(loader.getState().iframeEl).not.toBeNull());

    const iframe = loader.getState().iframeEl as HTMLIFrameElement;
    expect(iframe.tagName).toBe('IFRAME');
    expect(iframe.getAttribute('data-riq')).toBe('iframe');
    expect(iframe.src).toContain(CHATBOT_ID);
  });
});

// ── 4. postMessage origin validation ──────────────────────────────────────────

describe('postMessage origin validation', () => {
  let loader: ReturnType<typeof createLoader>;

  beforeEach(async () => {
    vi.stubGlobal('fetch', vi.fn()
      .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve({ data: fakeConfig }) })
      .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve(fakeConvResponse) }),
    );
    loader = createLoader();
    loader.processCommand('init', { chatbotId: CHATBOT_ID });
    await vi.waitFor(() => expect(loader.getState().launcher).not.toBeNull());

    loader.processCommand('open');
    // isOpen is set synchronously inside openWidget() before the first await.
    await vi.waitFor(() => expect(loader.getState().isOpen).toBe(true));
  });

  it('ignores riq:close from an untrusted origin', () => {
    expect(loader.getState().isOpen).toBe(true);

    window.dispatchEvent(
      new MessageEvent('message', {
        data:   { type: 'riq:close' },
        origin: 'https://evil.com',
      }),
    );

    expect(loader.getState().isOpen).toBe(true);
  });

  it('handles riq:close from the trusted widget origin', () => {
    expect(loader.getState().isOpen).toBe(true);

    window.dispatchEvent(
      new MessageEvent('message', {
        data:   { type: 'riq:close' },
        origin: WIDGET_ORIGIN,
      }),
    );

    expect(loader.getState().isOpen).toBe(false);
  });
});

// ── 5. destroy cleanup ─────────────────────────────────────────────────────────

describe('destroy', () => {
  let loader: ReturnType<typeof createLoader>;

  beforeEach(async () => {
    vi.stubGlobal('fetch', vi.fn()
      .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve({ data: fakeConfig }) })
      .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve(fakeConvResponse) }),
    );
    loader = createLoader();
    loader.processCommand('init', { chatbotId: CHATBOT_ID });
    await vi.waitFor(() => expect(loader.getState().launcher).not.toBeNull());

    loader.processCommand('open');
    await vi.waitFor(() => expect(loader.getState().iframeEl).not.toBeNull());
  });

  it('removes launcher and iframe from the DOM and clears state', () => {
    loader.processCommand('destroy');

    expect(loader.getState().launcher).toBeNull();
    expect(loader.getState().iframeEl).toBeNull();
    expect(loader.getState().isOpen).toBe(false);
    expect(document.getElementById('riq-launcher')).toBeNull();
    expect(document.getElementById('riq-widget')).toBeNull();
  });
});
