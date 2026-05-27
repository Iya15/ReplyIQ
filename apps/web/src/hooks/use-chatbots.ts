'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { chatbotsApi, ApiError } from '@/lib/api';
import type { StoreChatbotPayload, UpdateChatbotPayload, UpdateChatbotSettingsPayload } from '@replyiq/api-client';

const CHATBOTS_KEY = ['chatbots'] as const;
const chatbotKey = (id: string) => ['chatbots', id] as const;

export function useChatbots() {
  return useQuery({
    queryKey: CHATBOTS_KEY,
    queryFn: async () => {
      const res = await chatbotsApi.list();
      return res.data;
    },
  });
}

export function useChatbot(id: string) {
  return useQuery({
    queryKey: chatbotKey(id),
    queryFn: async () => {
      const res = await chatbotsApi.get(id);
      return res.data;
    },
    enabled: !!id,
  });
}

export function useCreateChatbot() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: StoreChatbotPayload) => chatbotsApi.create(data),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: CHATBOTS_KEY });
      toast.success(`"${res.data.name}" created successfully`);
    },
    onError: (err) => {
      const message = err instanceof ApiError ? err.message : 'Failed to create chatbot';
      toast.error(message);
    },
  });
}

export function useUpdateChatbot(id: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: UpdateChatbotPayload) => chatbotsApi.update(id, data),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: CHATBOTS_KEY });
      queryClient.setQueryData(chatbotKey(id), res.data);
      toast.success('Chatbot updated');
    },
    onError: (err) => {
      const message = err instanceof ApiError ? err.message : 'Failed to update chatbot';
      toast.error(message);
    },
  });
}

export function useUpdateChatbotSettings(id: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: UpdateChatbotSettingsPayload) => chatbotsApi.updateSettings(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: chatbotKey(id) });
      toast.success('Settings saved');
    },
    onError: (err) => {
      const message = err instanceof ApiError ? err.message : 'Failed to save settings';
      toast.error(message);
    },
  });
}

export function useDeleteChatbot() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => chatbotsApi.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: CHATBOTS_KEY });
      toast.success('Chatbot deleted');
    },
    onError: (err) => {
      const message = err instanceof ApiError ? err.message : 'Failed to delete chatbot';
      toast.error(message);
    },
  });
}
