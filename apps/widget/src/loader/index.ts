/**
 * ReplyIQ Loader — public/widget.js
 *
 * Tiny IIFE bundled for ES2018. Runs on the host page after the embed snippet:
 *
 *   <script>
 *     (function(d,s,o,f,js,fjs){
 *       d['ReplyIQ']=o;d[o]=d[o]||function(){(d[o].q=d[o].q||[]).push(arguments)};
 *       js=d.createElement(s);fjs=d.getElementsByTagName(s)[0];
 *       js.id=o;js.src=f;js.async=1;fjs.parentNode.insertBefore(js,fjs);
 *     }(document,'script','riq','https://cdn.replyiq.com/widget.js'));
 *     riq('init', { chatbotId: 'YOUR_CHATBOT_ID' });
 *   </script>
 *
 * No React, no external dependencies — plain DOM APIs only.
 */

// ── Types ──────────────────────────────────────────────────────────────────────

interface ChatbotConfig {
  name:            string;
  primary_color:   string;
  position:        'bottom-right' | 'bottom-left';
  welcome_message: string;
  avatar_url?:     string | null;
}

interface Session {
  token:          string;
  conversationId: string;
  expiresAt:      number;
}

interface VisitorInfo {
  email?: string;
  name?:  string;
}

interface RiqFn {
  (command: string, options?: unknown): void;
  q?: IArguments[];
}

declare global {
  interface Window { riq?: RiqFn; }
}

// ── Loader factory (exported for testability) ─────────────────────────────────

export interface LoaderState {
  isOpen:     boolean;
  chatbotId:  string;
  launcher:   HTMLElement | null;
  iframeEl:   HTMLIFrameElement | null;
}

export interface Loader {
  processCommand(command: string, options?: unknown): void;
  getState(): LoaderState;
}

export function createLoader(): Loader {

  // ── Module state ────────────────────────────────────────────────────────────

  let chatbotId    = '';
  let config: ChatbotConfig | null = null;
  let launcher:    HTMLElement | null = null;
  let iframeEl:    HTMLIFrameElement | null = null;
  let isOpen       = false;
  let iframeReady  = false;
  let session: Session | null = null;
  let visitor: VisitorInfo | null = null;
  let pendingToken: { token: string; conversationId: string } | null = null;
  let msgListener: ((e: MessageEvent) => void) | null = null;

  // ── Storage ─────────────────────────────────────────────────────────────────

  function getVisitorId(): string {
    try {
      const key = `riq_vid_${chatbotId}`;
      let id = localStorage.getItem(key);
      if (!id) {
        id = typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
          ? crypto.randomUUID()
          : [Math.random().toString(36).slice(2, 10), Date.now().toString(36)].join('-');
        localStorage.setItem(key, id);
      }
      return id;
    } catch {
      return Math.random().toString(36).slice(2);
    }
  }

  function loadSession(): Session | null {
    try {
      const raw = localStorage.getItem(`riq_session_${chatbotId}`);
      if (!raw) return null;
      const s = JSON.parse(raw) as Session;
      if (s.expiresAt > Date.now()) return s;
      localStorage.removeItem(`riq_session_${chatbotId}`);
    } catch {}
    return null;
  }

  function saveSession(s: Session): void {
    try { localStorage.setItem(`riq_session_${chatbotId}`, JSON.stringify(s)); } catch {}
  }

  // ── API ─────────────────────────────────────────────────────────────────────

  async function fetchConfig(): Promise<ChatbotConfig | null> {
    const url = `${__API_BASE__}/api/v1/public/chatbots/${chatbotId}/config`;
    for (let attempt = 0; attempt < 2; attempt++) {
      if (attempt > 0) await new Promise<void>(r => setTimeout(r, 5000));
      try {
        const res = await fetch(url);
        if (res.ok) return ((await res.json()) as { data: ChatbotConfig }).data;
      } catch {}
    }
    return null;
  }

  async function startConversation(): Promise<Session | null> {
    try {
      const res = await fetch(`${__API_BASE__}/api/v1/public/conversations`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ public_id: chatbotId, visitor_id: getVisitorId() }),
      });
      if (!res.ok) return null;
      const json = await res.json() as { session_token: string; data: { id: string } };
      return {
        token:          json.session_token,
        conversationId: json.data.id,
        expiresAt:      Date.now() + 23 * 60 * 60 * 1000, // 1 h before JWT expiry
      };
    } catch {
      return null;
    }
  }

  async function patchVisitor(s: Session, v: VisitorInfo): Promise<void> {
    try {
      await fetch(`${__API_BASE__}/api/v1/public/conversations/${s.conversationId}`, {
        method:  'PATCH',
        headers: {
          'Content-Type': 'application/json',
          Authorization:  `Bearer ${s.token}`,
        },
        body: JSON.stringify(v),
      });
    } catch {}
  }

  // ── postMessage ──────────────────────────────────────────────────────────────

  function widgetOrigin(): string {
    return new URL(__WIDGET_BASE__).origin;
  }

  function sendToIframe(msg: Record<string, unknown>): void {
    iframeEl?.contentWindow?.postMessage(msg, widgetOrigin());
  }

  function maybeFireInit(): void {
    if (!iframeReady || !pendingToken) return;
    sendToIframe({
      type:           'riq:init',
      token:          pendingToken.token,
      conversationId: pendingToken.conversationId,
      apiBase:        __API_BASE__,
      visitorId:      getVisitorId(),
    });
    if (visitor) sendToIframe({ type: 'riq:visitor', ...visitor });
    pendingToken = null;
  }

  // ── DOM helpers ──────────────────────────────────────────────────────────────

  function css(el: HTMLElement, props: Partial<CSSStyleDeclaration>): void {
    Object.assign(el.style, props);
  }

  function isMobile(): boolean {
    return typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches;
  }

  // ── Launcher ─────────────────────────────────────────────────────────────────

  function createLauncherEl(): void {
    if (!config || launcher) return;

    const btn = document.createElement('button');
    btn.id   = 'riq-launcher';
    btn.setAttribute('aria-label', `Chat with ${config.name}`);
    btn.setAttribute('data-riq', 'launcher');
    btn.tabIndex = 0;

    const side = config.position === 'bottom-left' ? 'left' : 'right';
    css(btn, {
      position:       'fixed',
      [side]:         '16px',
      bottom:         '16px',
      width:          '56px',
      height:         '56px',
      borderRadius:   '50%',
      background:     config.primary_color || '#4F46E5',
      border:         'none',
      cursor:         'pointer',
      zIndex:         '2147483647',
      display:        'flex',
      alignItems:     'center',
      justifyContent: 'center',
      boxShadow:      '0 4px 12px rgba(0,0,0,.2)',
      transition:     'transform .15s ease',
      padding:        '0',
      overflow:       'hidden',
    });

    if (config.avatar_url) {
      const img = document.createElement('img');
      img.src = config.avatar_url;
      img.alt = '';
      css(img, { width: '100%', height: '100%', objectFit: 'cover' });
      btn.appendChild(img);
    } else {
      btn.innerHTML =
        '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z" fill="white"/>' +
        '</svg>';
    }

    btn.addEventListener('mouseenter', () => { btn.style.transform = 'scale(1.1)'; });
    btn.addEventListener('mouseleave', () => { btn.style.transform = 'scale(1)'; });
    btn.addEventListener('click', () => { isOpen ? void closeWidget() : void openWidget(); });
    btn.addEventListener('keydown', (e: KeyboardEvent) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.click(); }
    });

    document.body.appendChild(btn);
    launcher = btn;
  }

  // ── Iframe ───────────────────────────────────────────────────────────────────

  function createIframeEl(): void {
    if (iframeEl) return;

    const iframe = document.createElement('iframe');
    iframe.id    = 'riq-widget';
    iframe.setAttribute('data-riq', 'iframe');
    iframe.src   = `${__WIDGET_BASE__}/${chatbotId}?v=${__BUILD_HASH__}`;
    // allow-same-origin is required so the app inside the iframe can use
    // sessionStorage and authenticate WebSocket presence channels.
    iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-forms allow-popups');
    iframe.setAttribute('allow', 'microphone');
    iframe.setAttribute('title', config?.name ?? 'Chat widget');

    const side = (config?.position ?? 'bottom-right') === 'bottom-left' ? 'left' : 'right';

    if (isMobile()) {
      css(iframe, {
        position: 'fixed', top: '0', left: '0', right: '0', bottom: '0',
        width: '100%', height: '100%', borderRadius: '0',
      });
    } else {
      css(iframe, {
        position: 'fixed',
        [side]:   '16px',
        bottom:   '82px',
        width:    '380px',
        height:   '600px',
        borderRadius: '16px',
      });
    }

    css(iframe, {
      border:     'none',
      zIndex:     '2147483646',
      display:    'none',
      opacity:    '0',
      transition: 'opacity .2s ease, transform .2s ease',
      transform:  'translateY(8px)',
      boxShadow:  '0 8px 32px rgba(0,0,0,.15)',
    });

    document.body.appendChild(iframe);
    iframeEl = iframe;
  }

  // ── Open / close ─────────────────────────────────────────────────────────────

  async function openWidget(): Promise<void> {
    if (!config) return;

    createIframeEl();

    if (iframeEl) {
      iframeEl.style.display = 'block';
      requestAnimationFrame(() => {
        if (iframeEl) {
          iframeEl.style.opacity   = '1';
          iframeEl.style.transform = 'translateY(0)';
        }
      });
    }

    isOpen = true;

    session = loadSession();
    if (!session) {
      session = await startConversation();
      if (session) saveSession(session);
    }

    if (session) {
      pendingToken = { token: session.token, conversationId: session.conversationId };
      maybeFireInit();
    }
  }

  function closeWidget(): void {
    if (!iframeEl) return;
    iframeEl.style.opacity   = '0';
    iframeEl.style.transform = 'translateY(8px)';
    setTimeout(() => {
      if (iframeEl) iframeEl.style.display = 'none';
    }, 200);
    isOpen = false;
  }

  // ── Message handling ──────────────────────────────────────────────────────────

  function onMessage(e: MessageEvent): void {
    if (e.origin !== widgetOrigin()) return;
    const data = e.data as { type?: unknown; height?: unknown; count?: unknown };
    if (!data || typeof data.type !== 'string') return;

    switch (data.type) {
      case 'riq:ready':
        iframeReady = true;
        maybeFireInit();
        break;
      case 'riq:close':
        closeWidget();
        break;
      case 'riq:resize':
        if (!isMobile() && iframeEl && typeof data.height === 'number') {
          iframeEl.style.height = `${Math.min(data.height, 700)}px`;
        }
        break;
      case 'riq:unread':
        break;
    }
  }

  function listenMessages(): void {
    if (msgListener) return;
    msgListener = (e: MessageEvent) => onMessage(e);
    window.addEventListener('message', msgListener);
  }

  // ── Commands ──────────────────────────────────────────────────────────────────

  async function cmdInit(options: unknown): Promise<void> {
    if (
      typeof options !== 'object' || options === null ||
      typeof (options as Record<string, unknown>)['chatbotId'] !== 'string' ||
      !(options as Record<string, unknown>)['chatbotId']
    ) {
      console.warn('[ReplyIQ] init() requires { chatbotId: string }');
      return;
    }

    chatbotId = (options as Record<string, unknown>)['chatbotId'] as string;
    listenMessages();

    config = await fetchConfig();
    if (!config) {
      console.warn('[ReplyIQ] failed to load chatbot config — widget disabled');
      return;
    }

    createLauncherEl();
  }

  function cmdSetVisitor(options: unknown): void {
    if (typeof options !== 'object' || options === null) return;
    const v = options as VisitorInfo;
    visitor = { ...(visitor ?? {}), ...v };

    if (session) void patchVisitor(session, visitor);
    sendToIframe({ type: 'riq:visitor', ...visitor });
  }

  function cmdDestroy(): void {
    launcher?.remove();
    iframeEl?.remove();
    if (msgListener) {
      window.removeEventListener('message', msgListener);
      msgListener = null;
    }
    launcher   = null;
    iframeEl   = null;
    isOpen     = false;
    iframeReady = false;
    config     = null;
    session    = null;
    visitor    = null;
  }

  function processCommand(command: string, options?: unknown): void {
    switch (command) {
      case 'init':       void cmdInit(options); break;
      case 'open':       void openWidget(); break;
      case 'close':      closeWidget(); break;
      case 'setVisitor': cmdSetVisitor(options); break;
      case 'destroy':    cmdDestroy(); break;
      default: console.warn('[ReplyIQ] unknown command:', command);
    }
  }

  return {
    processCommand,
    getState: () => ({ isOpen, chatbotId, launcher, iframeEl }),
  };
}

// ── Boot ───────────────────────────────────────────────────────────────────────
// Only runs in the IIFE bundle (not when imported by tests).

if (typeof window !== 'undefined') {
  const loader = createLoader();

  // Drain commands queued before this script loaded.
  // The embed snippet stores pre-load calls as: riq.q = riq.q || []; riq.q.push(arguments)
  const prior = window.riq;
  if (prior && typeof prior === 'function' && Array.isArray(prior.q)) {
    for (const args of prior.q) {
      if (typeof args[0] === 'string') {
        loader.processCommand(args[0] as string, args[1] as unknown);
      }
    }
  }

  // Replace stub with live handler.
  window.riq = function riq(command: string, options?: unknown): void {
    loader.processCommand(command, options);
  };
}
