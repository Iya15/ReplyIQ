import type { ApiClient } from '../client';
import type { ApiKey, ApiResponse, StoreApiKeyPayload } from '../types';

export function createApiKeyEndpoints(client: ApiClient) {
  return {
    // GET /api-keys
    list: () =>
      client.get<ApiResponse<ApiKey[]>>('/api-keys'),

    // POST /api-keys → returns full key in data.key (one time only)
    create: (payload: StoreApiKeyPayload) =>
      client.post<ApiResponse<ApiKey>>('/api-keys', payload),

    // DELETE /api-keys/{id}
    revoke: (id: string) =>
      client.delete<ApiResponse<{ message: string }>>(`/api-keys/${id}`),
  };
}
