import { useCallback, useEffect, useRef, useState } from 'react';
import { createEcho } from '../lib/echo';
import {
  getMessages,
  sendMessage as apiSendMessage,
  sendFeedback as apiSendFeedback,
  requestHuman as apiRequestHuman,
  RateLimitError,
} from '../lib/api';
import type { Message, Session, Source } from '../lib/types';

const POLL_INTERVAL_MS         = 1500;
const POLL_MAX                 = 40;
const WS_CONNECT_TIMEOUT       = 5000;
const FALLBACK_SUGGEST_AFTER   = 2;
const LOW_CONFIDENCE_THRESHOLD = 0.3;

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
interface EscalatedEvent {
  conversationId: string;
  initiatedBy:    string;
  agentName:      string | null;
}

export function useConversation(session: Session | null) {
  const [messages,                setMessages]                = useState<Message[]>([]);
  const [isPending,               setIsPending]               = useState(false);
  const [isLoading,               setIsLoading]               = useState(false);
  const [isEscalated,             setIsEscalated]             = useState(false);
  const [agentName,               setAgentName]               = useState<string | null>(null);
  const [shouldSuggestEscalation, setShouldSuggestEscalation] = useState(false);
  const [errorMessage,            setErrorMessage]            = useState<string | null>(null);

  const errorTimerRef        = useRef<ReturnType<typeof setTimeout> | null>(null);
  const pollRef              = useRef<ReturnType<typeof setInterval> | null>(null);
  const fallbackTimerRef     = useRef<ReturnType<typeof setTimeout>  | null>(null);
  const useFallbackRef       = useRef(false);
  const pendingIdRef         = useRef<string | null>(null);
  const consecutiveFallbacks = useRef(0);
  const startPollingRef      = useRef<((id: string) => void) | null>(null);

  useEffect(() => {
    if (!session) return;
    setIsLoading(true);
    getMessages(session.conversationId, session.token)
      .then(setMessages)
      .catch(() => {})
      .finally(() => setIsLoading(false));
  }, [session?.conversationId]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!session) return;
    const s = session;
    useFallbackRef.current = false;

    function stopPolling() {
      if (pollRef.current) { clearInterval(pollRef.current); pollRef.current = null; }
    }

    function startPolling(realAssistId: string) {
      stopPolling();
      let polls = 0;
      pollRef.current = setInterval(async () => {
        if (++polls > POLL_MAX) { stopPolling(); setIsPending(false); return; }
        try {
          const fresh = await getMessages(s.conversationId, s.token);
          setMessages(fresh);
          if (fresh.find(m => m.id === realAssistId)?.status === 'complete') {
            stopPolling(); pendingIdRef.current = null; setIsPending(false);
          }
        } catch {}
      }, POLL_INTERVAL_MS);
    }

    startPollingRef.current = startPolling;

    const echo = createEcho(session);

    fallbackTimerRef.current = setTimeout(() => {
      useFallbackRef.current = true;
      if (pendingIdRef.current) startPolling(pendingIdRef.current);
    }, WS_CONNECT_TIMEOUT);

    echo.connector.pusher.connection.bind('connected', () => {
      if (fallbackTimerRef.current) { clearTimeout(fallbackTimerRef.current); fallbackTimerRef.current = null; }
    });

    echo.join(`presence-chat.${session.conversationId}`)
      .listen('message.token', ({ messageId, token }: TokenEvent) => {
        setMessages(prev => prev.map(m => m.id === messageId ? { ...m, content: m.content + token } : m));
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

        // Track consecutive AI failures for escalation suggestion
        if (status === 'failed' || (confidence !== null && confidence < LOW_CONFIDENCE_THRESHOLD)) {
          consecutiveFallbacks.current += 1;
          if (consecutiveFallbacks.current >= FALLBACK_SUGGEST_AFTER) setShouldSuggestEscalation(true);
        } else {
          consecutiveFallbacks.current = 0;
        }
      })
      .listen('conversation.escalated', ({ agentName: name }: EscalatedEvent) => {
        setIsEscalated(true);
        setAgentName(name ?? null);
        setShouldSuggestEscalation(false);
        consecutiveFallbacks.current = 0;
        setMessages(prev => prev.filter(m => m.status !== 'pending'));
        setIsPending(false);
        stopPolling();
      });

    return () => {
      if (fallbackTimerRef.current) { clearTimeout(fallbackTimerRef.current); fallbackTimerRef.current = null; }
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

      const optimistic: Message[] = [
        { id: tempUserId, role: 'user', content, status: 'complete', sources: [], confidence: null, created_at: now },
        ...(!isEscalated
          ? [{ id: tempAssistId, role: 'assistant' as const, content: '', status: 'pending' as const, sources: [], confidence: null, created_at: now }]
          : []),
      ];
      setMessages(prev => [...prev, ...optimistic]);

      try {
        const result = await apiSendMessage(session.conversationId, session.token, content);

        if (!result.assistant_message) {
          setMessages(prev => prev.map(m => m.id === tempUserId ? result.user_message : m));
          setIsPending(false);
          return;
        }

        const realAssistId = result.assistant_message.id;
        pendingIdRef.current = realAssistId;
        setMessages(prev =>
          prev.map(m =>
            m.id === tempUserId   ? result.user_message :
            m.id === tempAssistId ? { ...result.assistant_message!, content: '' } :
            m,
          ),
        );
        if (useFallbackRef.current) startPollingRef.current?.(realAssistId);
      } catch (err) {
        setMessages(prev => prev.filter(m => m.id !== tempUserId && m.id !== tempAssistId));
        setIsPending(false);
        const msg = err instanceof RateLimitError ? 'Slow down a moment...' : 'Failed to send. Please try again.';
        setErrorMessage(msg);
        if (errorTimerRef.current) clearTimeout(errorTimerRef.current);
        errorTimerRef.current = setTimeout(() => setErrorMessage(null), 5000);
      }
    },
    [session, isPending, isEscalated],
  );

  const sendFeedback = useCallback(
    (msgId: string, feedback: 'helpful' | 'not_helpful') => {
      if (!session) return;
      void apiSendFeedback(msgId, session.token, feedback);
    },
    [session],
  );

  const escalate = useCallback(async () => {
    if (!session || isEscalated) return;
    try {
      await apiRequestHuman(session.conversationId, session.token);
      setIsEscalated(true);
      setShouldSuggestEscalation(false);
    } catch {}
  }, [session, isEscalated]);

  return { messages, sendMessage, sendFeedback, escalate, isPending, isLoading, isEscalated, agentName, shouldSuggestEscalation, errorMessage };
}
