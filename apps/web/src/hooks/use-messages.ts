'use client';

import { useEffect } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { conversationsApi } from '@/lib/api';
import { getEcho } from '@/lib/echo';
import { useAuthStore } from '@/lib/auth/auth-store';
import type { Message } from '@replyiq/api-client';

const messagesKey = (conversationId: string) => ['messages', conversationId] as const;

interface TokenEvent     { messageId: string; token: string }
interface CompletedEvent { messageId: string; content: string; status: string; sources: Message['sources']; confidence: number | null }

export function useMessages(conversationId: string | null) {
  const queryClient = useQueryClient();
  const token = useAuthStore((s) => s.token);

  const query = useQuery({
    queryKey: messagesKey(conversationId ?? ''),
    queryFn:  () => conversationsApi.messages(conversationId!),
    enabled:  !!conversationId,
    select:   (res) => res.data,
  });

  // Subscribe to the presence channel for real-time updates.
  useEffect(() => {
    if (!conversationId || !token) return;

    const echo    = getEcho(token);
    const channel = echo.join(`presence-chat.${conversationId}`);

    channel.listen('message.token', ({ messageId, token: tok }: TokenEvent) => {
      queryClient.setQueryData<Message[]>(messagesKey(conversationId), (prev) =>
        prev?.map((m) => m.id === messageId ? { ...m, content: m.content + tok } : m) ?? prev,
      );
    });

    channel.listen('message.completed', ({ messageId, content, status, sources, confidence }: CompletedEvent) => {
      queryClient.setQueryData<Message[]>(messagesKey(conversationId), (prev) =>
        prev?.map((m) =>
          m.id === messageId
            ? { ...m, content, status: status as Message['status'], sources, confidence }
            : m,
        ) ?? prev,
      );
    });

    return () => {
      echo.leave(`presence-chat.${conversationId}`);
    };
  }, [conversationId, token, queryClient]);

  return query;
}
