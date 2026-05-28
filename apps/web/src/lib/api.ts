import {
  createAnalyticsEndpoints,
  createApiClient,
  createApiKeyEndpoints,
  createAuthEndpoints,
  createBillingEndpoints,
  createChatbotEndpoints,
  createConversationEndpoints,
  createDocumentEndpoints,
  createTeamEndpoints,
} from '@replyiq/api-client';
import { useAuthStore } from './auth/auth-store';

function getToken(): string | null {
  return useAuthStore.getState().token;
}

function onUnauthorized(): void {
  useAuthStore.getState().logout();
  if (typeof window !== 'undefined') {
    window.location.href = '/login';
  }
}

const client = createApiClient({
  baseUrl: process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api/v1',
  getToken,
  onUnauthorized,
});

export const authApi          = createAuthEndpoints(client);
export const chatbotsApi      = createChatbotEndpoints(client);
export const documentsApi     = createDocumentEndpoints(client);
export const conversationsApi = createConversationEndpoints(client);
export const analyticsApi     = createAnalyticsEndpoints(client);
export const teamApi          = createTeamEndpoints(client);
export const apiKeysApi       = createApiKeyEndpoints(client);
export const billingApi       = createBillingEndpoints(client);

export { ApiError } from '@replyiq/api-client';
