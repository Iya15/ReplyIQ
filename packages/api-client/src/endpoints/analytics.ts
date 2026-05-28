import type { ApiClient } from '../client';
import type {
  AnalyticsFilters,
  AnalyticsOverview,
  ApiResponse,
  ConversationDataPoint,
  TopicCount,
  UnansweredQuestion,
} from '../types';

function buildQs(filters?: AnalyticsFilters): string {
  if (!filters) return '';
  const entries: Array<[string, string]> = [];
  if (filters.range !== undefined) entries.push(['range', filters.range]);
  if (filters.from  !== undefined) entries.push(['from',  filters.from]);
  if (filters.to    !== undefined) entries.push(['to',    filters.to]);
  return entries.length ? `?${new URLSearchParams(entries).toString()}` : '';
}

export function createAnalyticsEndpoints(client: ApiClient) {
  return {
    overview: (chatbotId: string, filters?: AnalyticsFilters) =>
      client.get<ApiResponse<AnalyticsOverview>>(
        `/chatbots/${chatbotId}/analytics/overview${buildQs(filters)}`,
      ),

    conversations: (chatbotId: string, filters?: AnalyticsFilters) =>
      client.get<ApiResponse<ConversationDataPoint[]>>(
        `/chatbots/${chatbotId}/analytics/conversations${buildQs(filters)}`,
      ),

    topics: (chatbotId: string, filters?: AnalyticsFilters) =>
      client.get<ApiResponse<TopicCount[]>>(
        `/chatbots/${chatbotId}/analytics/topics${buildQs(filters)}`,
      ),

    unanswered: (chatbotId: string, filters?: AnalyticsFilters) =>
      client.get<ApiResponse<UnansweredQuestion[]>>(
        `/chatbots/${chatbotId}/analytics/unanswered${buildQs(filters)}`,
      ),
  };
}
