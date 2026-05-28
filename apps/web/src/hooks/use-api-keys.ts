'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { apiKeysApi, ApiError } from '@/lib/api';
import type { StoreApiKeyPayload } from '@replyiq/api-client';

const API_KEYS_KEY = ['api-keys'] as const;

export function useApiKeys() {
  return useQuery({
    queryKey: API_KEYS_KEY,
    queryFn:  () => apiKeysApi.list(),
  });
}

export function useCreateApiKey() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: StoreApiKeyPayload) => apiKeysApi.create(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: API_KEYS_KEY }),
    onError: (err) => {
      toast.error(err instanceof ApiError ? err.message : 'Failed to create API key');
    },
  });
}

export function useRevokeApiKey() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => apiKeysApi.revoke(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: API_KEYS_KEY });
      toast.success('API key revoked');
    },
    onError: (err) => {
      toast.error(err instanceof ApiError ? err.message : 'Failed to revoke API key');
    },
  });
}
