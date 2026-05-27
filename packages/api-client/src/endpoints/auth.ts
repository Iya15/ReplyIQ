import type { ApiClient } from '../client';
import type {
  ApiResponse,
  LoginPayload,
  Me,
  RegisterPayload,
  ResetPasswordPayload,
  User,
} from '../types';

export function createAuthEndpoints(client: ApiClient) {
  return {
    // POST /auth/register → 201 { data: { token, user } }
    register: (data: RegisterPayload) =>
      client.post<ApiResponse<{ token: string; user: User }>>('/auth/register', data),

    // POST /auth/login → { data: { token, user } }
    login: (data: LoginPayload) =>
      client.post<ApiResponse<{ token: string; user: User }>>('/auth/login', data),

    // POST /auth/logout → { data: { message } }  (requires auth)
    logout: () =>
      client.post<ApiResponse<{ message: string }>>('/auth/logout', {}),

    // GET /auth/me → { data: Me }  (requires auth)
    me: () =>
      client.get<ApiResponse<Me>>('/auth/me'),

    // POST /auth/forgot-password → { data: { message } }
    forgotPassword: (email: string) =>
      client.post<ApiResponse<{ message: string }>>('/auth/forgot-password', { email }),

    // POST /auth/reset-password → { data: { message } }
    resetPassword: (data: ResetPasswordPayload) =>
      client.post<ApiResponse<{ message: string }>>('/auth/reset-password', data),

    // GET /auth/verify-email/{id}/{hash}?expires=...&signature=...
    // The signed URL is emailed to the user; the frontend extracts id, hash,
    // expires, signature from the query string and calls this.
    verifyEmail: (id: string, hash: string, query: { expires: string; signature: string }) => {
      const params = new URLSearchParams(query);
      return client.get<ApiResponse<{ message: string }>>(
        `/auth/verify-email/${id}/${hash}?${params.toString()}`,
      );
    },
  };
}
