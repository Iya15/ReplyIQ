'use client';

import { useQuery } from '@tanstack/react-query';
import { analyticsApi } from '@/lib/api';
import type { AnalyticsFilters } from '@replyiq/api-client';

const analyticsKey = (chatbotId: string, endpoint: string, filters?: AnalyticsFilters) =>
  ['analytics', chatbotId, endpoint, filters] as const;

export function useAnalyticsOverview(chatbotId: string, filters?: AnalyticsFilters) {
  return useQuery({
    queryKey: analyticsKey(chatbotId, 'overview', filters),
    queryFn:  () => analyticsApi.overview(chatbotId, filters),
    enabled:  !!chatbotId,
  });
}

export function useAnalyticsConversations(chatbotId: string, filters?: AnalyticsFilters) {
  return useQuery({
    queryKey: analyticsKey(chatbotId, 'conversations', filters),
    queryFn:  () => analyticsApi.conversations(chatbotId, filters),
    enabled:  !!chatbotId,
  });
}

export function useAnalyticsTopics(chatbotId: string, filters?: AnalyticsFilters) {
  return useQuery({
    queryKey: analyticsKey(chatbotId, 'topics', filters),
    queryFn:  () => analyticsApi.topics(chatbotId, filters),
    enabled:  !!chatbotId,
  });
}

export function useAnalyticsUnanswered(chatbotId: string, filters?: AnalyticsFilters) {
  return useQuery({
    queryKey: analyticsKey(chatbotId, 'unanswered', filters),
    queryFn:  () => analyticsApi.unanswered(chatbotId, filters),
    enabled:  !!chatbotId,
  });
}
