import type { ApiClient } from '../client';
import type {
  ApiResponse,
  Document,
  DocumentFilters,
  Paginated,
  StoreDocumentTextPayload,
} from '../types';

export function createDocumentEndpoints(client: ApiClient) {
  return {
    // GET /chatbots/{chatbotId}/documents?status=...
    list: (chatbotId: string, filters?: DocumentFilters) => {
      const qs = filters?.status ? `?status=${filters.status}` : '';
      return client.get<Paginated<Document>>(`/chatbots/${chatbotId}/documents${qs}`);
    },

    // POST /chatbots/{chatbotId}/documents (multipart/form-data)
    uploadFile: (chatbotId: string, formData: FormData) =>
      client.postForm<ApiResponse<Document>>(`/chatbots/${chatbotId}/documents`, formData),

    // POST /chatbots/{chatbotId}/documents/text
    addText: (chatbotId: string, data: StoreDocumentTextPayload) =>
      client.post<ApiResponse<Document>>(`/chatbots/${chatbotId}/documents/text`, data),

    // DELETE /documents/{id}
    delete: (id: string) =>
      client.delete<ApiResponse<{ message: string }>>(`/documents/${id}`),

    // POST /documents/{id}/reprocess → 202 { data: Document }
    reprocess: (id: string) =>
      client.post<ApiResponse<Document>>(`/documents/${id}/reprocess`, {}),
  };
}
