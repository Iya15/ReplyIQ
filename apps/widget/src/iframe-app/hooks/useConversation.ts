import { useCallback, useEffect, useRef, useState } from 'react';
import { getMessages, sendMessage as apiSendMessage, sendFeedback as apiSendFeedback } from '../lib/api';
import type { Message, Session } from '../lib/types';

const POLL_INTERVAL_MS = 1500;
const POLL_MAX         = 40; // 60 s total

export function useConversation(session: Session | null) {
  const [messages,  setMessages]  = useState<Message[]>([]);
  const [isPending, setIsPending] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // Load messages when session becomes available / changes.
  useEffect(() => {
    if (!session) return;

    setIsLoading(true);
    getMessages(session.conversationId, session.token)
      .then(setMessages)
      .catch(() => {})
      .finally(() => setIsLoading(false));
  }, [session?.conversationId, session?.token]); // eslint-disable-line react-hooks/exhaustive-deps

  // Clear interval on unmount.
  useEffect(() => () => { if (pollRef.current) clearInterval(pollRef.current); }, []);

  const sendMessage = useCallback(
    async (content: string) => {
      if (!session || isPending || !content.trim()) return;

      setIsPending(true);

      // Optimistic messages while the real ones are in flight.
      const tempUserId   = `_opt_user_${Date.now()}`;
      const tempAssistId = `_opt_asst_${Date.now()}`;
      const now          = new Date().toISOString();

      setMessages(prev => [
        ...prev,
        { id: tempUserId,   role: 'user',      content,  status: 'complete', sources: [], confidence: null, created_at: now },
        { id: tempAssistId, role: 'assistant',  content: '', status: 'pending',  sources: [], confidence: null, created_at: now },
      ]);

      try {
        const result = await apiSendMessage(session.conversationId, session.token, content);
        const realAssistId = result.assistant_message.id;

        // Replace optimistic placeholders with real server messages.
        setMessages(prev =>
          prev.map(m =>
            m.id === tempUserId   ? result.user_message :
            m.id === tempAssistId ? result.assistant_message :
            m,
          ),
        );

        // Poll until the assistant message transitions to 'complete'.
        let polls = 0;
        pollRef.current = setInterval(async () => {
          polls += 1;
          if (polls > POLL_MAX) {
            clearInterval(pollRef.current!);
            pollRef.current = null;
            setIsPending(false);
            return;
          }
          try {
            const fresh = await getMessages(session.conversationId, session.token);
            setMessages(fresh);
            const assistant = fresh.find(m => m.id === realAssistId);
            if (assistant?.status === 'complete') {
              clearInterval(pollRef.current!);
              pollRef.current = null;
              setIsPending(false);
            }
          } catch {}
        }, POLL_INTERVAL_MS);
      } catch {
        // Remove optimistic messages on failure.
        setMessages(prev => prev.filter(m => m.id !== tempUserId && m.id !== tempAssistId));
        setIsPending(false);
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

  return { messages, sendMessage, sendFeedback, isPending, isLoading };
}
