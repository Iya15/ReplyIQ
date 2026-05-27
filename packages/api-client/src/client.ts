import { ApiError } from './types';

export interface ApiClientConfig {
  baseUrl: string;
  /** Return the current bearer token, or null if unauthenticated. */
  getToken: () => string | null;
  /** Called when the API returns 401 — use to redirect to login. */
  onUnauthorized?: () => void;
}

export function createApiClient(config: ApiClientConfig) {
  const { baseUrl, getToken, onUnauthorized } = config;

  async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
    const token = getToken();
    const headers: Record<string, string> = { 'Content-Type': 'application/json' };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const res = await fetch(`${baseUrl}${path}`, {
      method,
      headers,
      credentials: 'include',
      // Spread avoids passing `undefined` to `body`, which fails exactOptionalPropertyTypes.
      ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
    });

    if (res.status === 401) {
      onUnauthorized?.();
      throw new ApiError('unauthorized', 'Authentication required.', 401);
    }

    const json = await res.json() as Record<string, unknown>;

    if (!res.ok) {
      const err = json['error'] as { code?: string; message?: string } | undefined;
      throw new ApiError(
        err?.code ?? 'unknown_error',
        err?.message ?? `Request failed with status ${res.status}`,
        res.status,
      );
    }

    return json as T;
  }

  // Separate handler for FormData — omits Content-Type so the browser sets the
  // multipart boundary automatically. Used for file uploads.
  async function requestForm<T>(method: string, path: string, body: FormData): Promise<T> {
    const token = getToken();
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const res = await fetch(`${baseUrl}${path}`, {
      method,
      headers,
      credentials: 'include',
      body,
    });

    if (res.status === 401) {
      onUnauthorized?.();
      throw new ApiError('unauthorized', 'Authentication required.', 401);
    }

    const json = await res.json() as Record<string, unknown>;

    if (!res.ok) {
      const err = json['error'] as { code?: string; message?: string } | undefined;
      throw new ApiError(
        err?.code ?? 'unknown_error',
        err?.message ?? `Request failed with status ${res.status}`,
        res.status,
      );
    }

    return json as T;
  }

  return {
    get:      <T>(path: string)                    => request<T>('GET',    path),
    post:     <T>(path: string, body: unknown)     => request<T>('POST',   path, body),
    patch:    <T>(path: string, body: unknown)     => request<T>('PATCH',  path, body),
    delete:   <T>(path: string)                    => request<T>('DELETE', path),
    postForm: <T>(path: string, body: FormData)    => requestForm<T>('POST', path, body),
  };
}

export type ApiClient = ReturnType<typeof createApiClient>;
