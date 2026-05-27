import type { ApiClient } from '../client';
import type {
  ApiResponse,
  Chatbot,
  ChatbotSettings,
  EmbedCode,
  ListParams,
  Paginated,
  StoreChatbotPayload,
  UpdateChatbotPayload,
  UpdateChatbotSettingsPayload,
} from '../types';

function buildQs(params?: ListParams): string {
  if (!params) return '';
  const entries: Array<[string, string]> = [];
  if (params.page !== undefined) entries.push(['page', String(params.page)]);
  if (params.per_page !== undefined) entries.push(['per_page', String(params.per_page)]);
  return entries.length ? `?${new URLSearchParams(entries).toString()}` : '';
}

export function createChatbotEndpoints(client: ApiClient) {
  return {
    // GET /chatbots → Paginated<Chatbot>
    list: (params?: ListParams) =>
      client.get<Paginated<Chatbot>>(`/chatbots${buildQs(params)}`),

    // GET /chatbots/{id} → { data: Chatbot }
    get: (id: string) =>
      client.get<ApiResponse<Chatbot>>(`/chatbots/${id}`),

    // POST /chatbots → 201 { data: Chatbot }
    create: (data: StoreChatbotPayload) =>
      client.post<ApiResponse<Chatbot>>('/chatbots', data),

    // PATCH /chatbots/{id} → { data: Chatbot }
    update: (id: string, data: UpdateChatbotPayload) =>
      client.patch<ApiResponse<Chatbot>>(`/chatbots/${id}`, data),

    // DELETE /chatbots/{id} → { data: { message } }
    delete: (id: string) =>
      client.delete<ApiResponse<{ message: string }>>(`/chatbots/${id}`),

    // GET /chatbots/{id}/embed-code → { data: EmbedCode }
    getEmbedCode: (id: string) =>
      client.get<ApiResponse<EmbedCode>>(`/chatbots/${id}/embed-code`),

    // GET /chatbots/{id}/settings → { data: ChatbotSettings }
    getSettings: (id: string) =>
      client.get<ApiResponse<ChatbotSettings>>(`/chatbots/${id}/settings`),

    // PATCH /chatbots/{id}/settings → { data: ChatbotSettings }
    updateSettings: (id: string, data: UpdateChatbotSettingsPayload) =>
      client.patch<ApiResponse<ChatbotSettings>>(`/chatbots/${id}/settings`, data),
  };
}
