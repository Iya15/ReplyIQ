'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { conversationsApi, ApiError } from '@/lib/api';
import type { Conversation, ConversationFilters } from '@replyiq/api-client';

// ── Query keys ────────────────────────────────────────────────────────────────

export const conversationsKey = (chatbotId: string, filters?: ConversationFilters) =>
  filters
    ? (['conversations', chatbotId, filters] as const)
    : (['conversations', chatbotId] as const);

export const conversationKey = (id: string) => ['conversation', id] as const;

// ── Hooks ─────────────────────────────────────────────────────────────────────

export function useConversations(chatbotId: string, filters?: ConversationFilters) {
  return useQuery({
    queryKey: conversationsKey(chatbotId, filters),
    queryFn:  () => conversationsApi.list(chatbotId, filters),
    enabled:  !!chatbotId,
  });
}

export function useConversation(id: string | null) {
  return useQuery({
    queryKey: conversationKey(id ?? ''),
    queryFn:  () => conversationsApi.get(id!),
    enabled:  !!id,
  });
}

export function useResolveConversation(chatbotId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => conversationsApi.resolve(id),

    onSuccess: (res, id) => {
      // Update the conversation in the list cache
      queryClient.setQueriesData<{ data: Conversation[] }>(
        { queryKey: ['conversations', chatbotId] },
        (old) =>
          old
            ? { ...old, data: old.data.map((c) => (c.id === id ? res.data : c)) }
            : old,
      );
      // Update the single-conversation cache
      queryClient.setQueryData(conversationKey(id), res);
      toast.success('Conversation marked as resolved');
    },

    onError: (err) => {
      toast.error(err instanceof ApiError ? err.message : 'Failed to resolve conversation');
    },
  });
}
