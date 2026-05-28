import { useCallback, useEffect, useRef, useState } from 'react';
import { createEcho } from '../lib/echo';
import { getMessages, sendMessage as apiSendMessage, sendFeedback as apiSendFeedback, RateLimitError } from '../lib/api';
import type { Message, Session, Source } from '../lib/types';

const POLL_INTERVAL_MS   = 1500;
const POLL_MAX           = 40;   // 60 s
const WS_CONNECT_TIMEOUT = 5000; // fall back to polling after 5 s

// Shape of WebSocket event payloads (mirroring PHP event public properties)
interface TokenEvent {
  messageId: string;
  token:     string;
}
interface CompletedEvent {
  messageId:  string;
  content:    string;
  status:     string;
  sources:    Source[];
  confidence: number | null;
}

export function useConversation(session: Session | null) {
  const [messages,      setMessages]      = useState<Message[]>([]);
  const [isPending,     setIsPending]     = useState(false);
  const [isLoading,     setIsLoading]     = useState(false);
  const [errorMessage,  setErrorMessage]  = useState<string | null>(null);
  const errorTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const pollRef          = useRef<ReturnType<typeof setInterval> | null>(null);
  const fallbackTimerRef = useRef<ReturnType<typeof setTimeout>  | null>(null);
  const useFallbackRef   = useRef(false);
  const pendingIdRef     = useRef<string | null>(null);
  // Ref so sendMessage can always call the current startPolling closure
  const startPollingRef  = useRef<((id: string) => void) | null>(null);

  // Load messages when session first becomes available.
  useEffect(() => {
    if (!session) return;
    setIsLoading(true);
    getMessages(session.conversationId, session.token)
      .then(setMessages)
      .catch(() => {})
      .finally(() => setIsLoading(false));
  }, [session?.conversationId]); // eslint-disable-line react-hooks/exhaustive-deps

  // WebSocket subscription — re-runs only when the conversation/token changes.
  useEffect(() => {
    if (!session) return;

    // Capture as non-null so nested async closures don't see Session | null
    const s = session;

    useFallbackRef.current = false;

    // ── polling helpers (closed over session from this effect run) ──────────

    function stopPolling() {
      if (pollRef.current) {
        clearInterval(pollRef.current);
        pollRef.current = null;
      }
    }

    function startPolling(realAssistId: string) {
      stopPolling();
      let polls = 0;
      pollRef.current = setInterval(async () => {
        if (++polls > POLL_MAX) {
          stopPolling();
          setIsPending(false);
          return;
        }
        try {
          const fresh = await getMessages(s.conversationId, s.token);
          setMessages(fresh);
          if (fresh.find(m => m.id === realAssistId)?.status === 'complete') {
            stopPolling();
            pendingIdRef.current = null;
            setIsPending(false);
          }
        } catch {}
      }, POLL_INTERVAL_MS);
    }

    // Expose startPolling so sendMessage can invoke it when in fallback mode
    startPollingRef.current = startPolling;

    // ── Echo instance ────────────────────────────────────────────────────────

    const echo = createEcho(session);

    // Start the 5-second connection window; activate polling if WS doesn't connect
    fallbackTimerRef.current = setTimeout(() => {
      useFallbackRef.current = true;
      if (pendingIdRef.current) startPolling(pendingIdRef.current);
    }, WS_CONNECT_TIMEOUT);

    echo.connector.pusher.connection.bind('connected', () => {
      if (fallbackTimerRef.current) {
        clearTimeout(fallbackTimerRef.current);
        fallbackTimerRef.current = null;
      }
    });

    echo.join(`presence-chat.${session.conversationId}`)
      .listen('message.token', ({ messageId, token }: TokenEvent) => {
        setMessages(prev =>
          prev.map(m => m.id === messageId ? { ...m, content: m.content + token } : m),
        );
      })
      .listen('message.completed', ({ messageId, content, status, sources, confidence }: CompletedEvent) => {
        setMessages(prev =>
          prev.map(m =>
            m.id === messageId
              ? { ...m, content, status: status as Message['status'], sources, confidence }
              : m,
          ),
        );
        pendingIdRef.current = null;
        setIsPending(false);
        stopPolling();
      });

    return () => {
      if (fallbackTimerRef.current) {
        clearTimeout(fallbackTimerRef.current);
        fallbackTimerRef.current = null;
      }
      stopPolling();
      echo.leave(`presence-chat.${session.conversationId}`);
      echo.disconnect();
      startPollingRef.current = null;
    };
  }, [session?.conversationId, session?.token]); // eslint-disable-line react-hooks/exhaustive-deps

  const sendMessage = useCallback(
    async (content: string) => {
      if (!session || isPending || !content.trim()) return;

      setIsPending(true);

      const tempUserId   = `_opt_user_${Date.now()}`;
      const tempAssistId = `_opt_asst_${Date.now()}`;
      const now          = new Date().toISOString();

      setMessages(prev => [
        ...prev,
        { id: tempUserId,   role: 'user',      content,  status: 'complete', sources: [], confidence: null, created_at: now },
        { id: tempAssistId, role: 'assistant',  content: '', status: 'pending',  sources: [], confidence: null, created_at: now },
      ]);

      try {
        const result       = await apiSendMessage(session.conversationId, session.token, content);
        const realAssistId = result.assistant_message.id;

        pendingIdRef.current = realAssistId;

        // Replace optimistic stubs with real IDs; keep content empty — WS fills it.
        setMessages(prev =>
          prev.map(m =>
            m.id === tempUserId   ? result.user_message :
            m.id === tempAssistId ? { ...result.assistant_message, content: '' } :
            m,
          ),
        );

        // WS handles the response; polling only if the connection timed out.
        if (useFallbackRef.current) {
          startPollingRef.current?.(realAssistId);
        }
      } catch (err) {
        setMessages(prev => prev.filter(m => m.id !== tempUserId && m.id !== tempAssistId));
        setIsPending(false);

        const msg = err instanceof RateLimitError
          ? 'Slow down a moment...'
          : 'Failed to send. Please try again.';
        setErrorMessage(msg);
        if (errorTimerRef.current) clearTimeout(errorTimerRef.current);
        errorTimerRef.current = setTimeout(() => setErrorMessage(null), 5000);
      }
    },
    [session, isPending],
  );

  const sendFeedback = useCallback(
    (msgId: string, feedback: 'helpful' | 'not_helpful') => {
      if (!session) return;
      void apiSendFeedback(msgId, session.token, feedback);
    },
    [session],
  );

  return { messages, sendMessage, sendFeedback, isPending, isLoading, errorMessage };
}
