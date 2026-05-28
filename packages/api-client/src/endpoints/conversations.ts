import type { ApiClient } from '../client';
import type {
  ApiResponse,
  Conversation,
  ConversationFilters,
  Message,
  Paginated,
} from '../types';

function buildQs(filters?: ConversationFilters): string {
  if (!filters) return '';
  const entries: Array<[string, string]> = [];
  if (filters.status   !== undefined) entries.push(['status',   filters.status]);
  if (filters.search   !== undefined) entries.push(['search',   filters.search]);
  if (filters.page     !== undefined) entries.push(['page',     String(filters.page)]);
  if (filters.per_page !== undefined) entries.push(['per_page', String(filters.per_page)]);
  return entries.length ? `?${new URLSearchParams(entries).toString()}` : '';
}

export function createConversationEndpoints(client: ApiClient) {
  return {
    // GET /chatbots/{id}/conversations → Paginated<Conversation>
    list: (chatbotId: string, filters?: ConversationFilters) =>
      client.get<Paginated<Conversation>>(`/chatbots/${chatbotId}/conversations${buildQs(filters)}`),

    // GET /conversations/{id} → { data: Conversation }
    get: (id: string) =>
      client.get<ApiResponse<Conversation>>(`/conversations/${id}`),

    // GET /conversations/{id}/messages → { data: Message[] }
    messages: (id: string) =>
      client.get<ApiResponse<Message[]>>(`/conversations/${id}/messages`),

    // POST /conversations/{id}/resolve → { data: Conversation }
    resolve: (id: string) =>
      client.post<ApiResponse<Conversation>>(`/conversations/${id}/resolve`, {}),
  };
}
