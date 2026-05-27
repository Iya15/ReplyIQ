'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { documentsApi, ApiError } from '@/lib/api';
import { useAuthStore } from '@/lib/auth/auth-store';
import type {
  Document,
  DocumentFilters,
  DocumentSourceType,
  Paginated,
  StoreDocumentTextPayload,
  StoreDocumentUrlPayload,
} from '@replyiq/api-client';

// ── Query keys ────────────────────────────────────────────────────────────────

const documentsKey = (chatbotId: string, filters?: DocumentFilters) =>
  filters ? (['documents', chatbotId, filters] as const) : (['documents', chatbotId] as const);

// ── Helpers ───────────────────────────────────────────────────────────────────

function inferSourceType(filename: string): DocumentSourceType {
  const ext = filename.split('.').pop()?.toLowerCase();
  if (ext === 'pdf') return 'pdf';
  if (ext === 'docx' || ext === 'doc') return 'docx';
  return 'txt';
}

function makeOptimisticDocument(file: File): Document {
  return {
    id: `optimistic-${Date.now()}`,
    title: file.name.replace(/\.[^.]+$/, ''),
    source_type: inferSourceType(file.name),
    source_url: null,
    status: 'pending',
    error_message: null,
    char_count: null,
    chunk_count: null,
    metadata: null,
    created_at: new Date().toISOString(),
    processed_at: null,
  };
}

// XHR-based upload so we get byte-level progress events that fetch can't provide.
function uploadWithProgress(
  chatbotId: string,
  formData: FormData,
  onProgress: (pct: number) => void,
): Promise<Document> {
  const token = useAuthStore.getState().token;
  const baseUrl = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api/v1';

  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();

    xhr.upload.addEventListener('progress', (e) => {
      if (e.lengthComputable) {
        onProgress(Math.round((e.loaded / e.total) * 100));
      }
    });

    xhr.addEventListener('load', () => {
      if (xhr.status === 202) {
        const json = JSON.parse(xhr.responseText) as { data: Document };
        resolve(json.data);
      } else {
        const json = JSON.parse(xhr.responseText) as { error?: { code: string; message: string } };
        reject(
          new ApiError(
            json.error?.code ?? 'upload_error',
            json.error?.message ?? 'Upload failed',
            xhr.status,
          ),
        );
      }
    });

    xhr.addEventListener('error', () => {
      reject(new ApiError('network_error', 'Upload failed', 0));
    });

    xhr.open('POST', `${baseUrl}/chatbots/${chatbotId}/documents`);
    xhr.setRequestHeader('Accept', 'application/json');
    if (token) xhr.setRequestHeader('Authorization', `Bearer ${token}`);
    xhr.send(formData);
  });
}

// ── Hooks ─────────────────────────────────────────────────────────────────────

export function useDocuments(chatbotId: string, filters?: DocumentFilters) {
  return useQuery({
    queryKey: documentsKey(chatbotId, filters),
    queryFn: () => documentsApi.list(chatbotId, filters),
    enabled: !!chatbotId,
    refetchInterval: (query) => {
      const docs = (query.state.data as Paginated<Document> | undefined)?.data ?? [];
      // File/text/manual docs process in seconds → poll at 3s.
      const hasFileActive = docs.some(
        (d) => d.source_type !== 'url' && (d.status === 'pending' || d.status === 'processing'),
      );
      if (hasFileActive) return 3_000;
      // URL crawls can take 5–15 minutes → 5s is frequent enough.
      const hasUrlActive = docs.some(
        (d) => d.source_type === 'url' && (d.status === 'pending' || d.status === 'processing'),
      );
      return hasUrlActive ? 5_000 : false;
    },
  });
}

export function useUploadDocument(chatbotId: string) {
  const [progress, setProgress] = useState(0);
  const queryClient = useQueryClient();

  const mutation = useMutation({
    mutationFn: (formData: FormData) => uploadWithProgress(chatbotId, formData, setProgress),

    onMutate: async (formData: FormData) => {
      setProgress(0);
      await queryClient.cancelQueries({ queryKey: documentsKey(chatbotId) });
      const snapshot = queryClient.getQueryData<Paginated<Document>>(documentsKey(chatbotId));

      const file = formData.get('file') as File | null;
      if (file) {
        const optimistic = makeOptimisticDocument(file);
        queryClient.setQueryData<Paginated<Document>>(documentsKey(chatbotId), (old) =>
          old ? { ...old, data: [optimistic, ...old.data] } : old,
        );
      }

      return { snapshot };
    },

    onError: (err, _vars, context) => {
      if (context?.snapshot !== undefined) {
        queryClient.setQueryData(documentsKey(chatbotId), context.snapshot);
      }
      toast.error(err instanceof ApiError ? err.message : 'Upload failed');
    },

    onSuccess: () => {
      toast.success('Document uploaded');
    },

    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: ['documents', chatbotId] });
    },
  });

  return { ...mutation, progress };
}

export function useAddTextDocument(chatbotId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: StoreDocumentTextPayload) => documentsApi.addText(chatbotId, data),

    onMutate: async (data: StoreDocumentTextPayload) => {
      await queryClient.cancelQueries({ queryKey: documentsKey(chatbotId) });
      const snapshot = queryClient.getQueryData<Paginated<Document>>(documentsKey(chatbotId));

      const optimistic: Document = {
        id: `optimistic-${Date.now()}`,
        title: data.title,
        source_type: 'manual',
        source_url: null,
        status: 'pending',
        error_message: null,
        char_count: null,
        chunk_count: null,
        metadata: null,
        created_at: new Date().toISOString(),
        processed_at: null,
      };

      queryClient.setQueryData<Paginated<Document>>(documentsKey(chatbotId), (old) =>
        old ? { ...old, data: [optimistic, ...old.data] } : old,
      );

      return { snapshot };
    },

    onError: (err, _vars, context) => {
      if (context?.snapshot !== undefined) {
        queryClient.setQueryData(documentsKey(chatbotId), context.snapshot);
      }
      toast.error(err instanceof ApiError ? err.message : 'Failed to add document');
    },

    onSuccess: () => {
      toast.success('Document added');
    },

    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: ['documents', chatbotId] });
    },
  });
}

export function useDeleteDocument(chatbotId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => documentsApi.delete(id),

    onMutate: async (id: string) => {
      await queryClient.cancelQueries({ queryKey: ['documents', chatbotId] });
      // Remove from all filter variants for this chatbot.
      queryClient.setQueriesData<Paginated<Document>>(
        { queryKey: ['documents', chatbotId] },
        (old) => (old ? { ...old, data: old.data.filter((d) => d.id !== id) } : old),
      );
    },

    onError: (err) => {
      // Rollback by refetching — simpler than snapshotting all filter variants.
      void queryClient.invalidateQueries({ queryKey: ['documents', chatbotId] });
      toast.error(err instanceof ApiError ? err.message : 'Failed to delete document');
    },

    onSuccess: () => {
      toast.success('Document deleted');
    },

    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: ['documents', chatbotId] });
    },
  });
}

export function useAddUrlDocument(chatbotId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: StoreDocumentUrlPayload) => documentsApi.addUrl(chatbotId, data),

    onMutate: async (data: StoreDocumentUrlPayload) => {
      await queryClient.cancelQueries({ queryKey: documentsKey(chatbotId) });
      const snapshot = queryClient.getQueryData<Paginated<Document>>(documentsKey(chatbotId));

      // Derive a provisional title from the URL hostname while the job runs.
      let title = data.url;
      try { title = new URL(data.url).hostname; } catch { /* leave as full URL */ }

      const optimistic: Document = {
        id: `optimistic-${Date.now()}`,
        title,
        source_type: 'url',
        source_url: data.url,
        status: 'pending',
        error_message: null,
        char_count: null,
        chunk_count: null,
        metadata: null,
        created_at: new Date().toISOString(),
        processed_at: null,
      };

      queryClient.setQueryData<Paginated<Document>>(documentsKey(chatbotId), (old) =>
        old ? { ...old, data: [optimistic, ...old.data] } : old,
      );

      return { snapshot };
    },

    onError: (err, _vars, context) => {
      if (context?.snapshot !== undefined) {
        queryClient.setQueryData(documentsKey(chatbotId), context.snapshot);
      }
      toast.error(err instanceof ApiError ? err.message : 'Failed to start crawl');
    },

    onSuccess: () => {
      toast.success('Crawl started — this may take a few minutes');
    },

    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: ['documents', chatbotId] });
    },
  });
}

export function useReprocessDocument(chatbotId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => documentsApi.reprocess(id),

    onSuccess: (_res, id) => {
      // Flip the row to pending immediately so polling kicks in.
      queryClient.setQueriesData<Paginated<Document>>(
        { queryKey: ['documents', chatbotId] },
        (old) =>
          old
            ? {
                ...old,
                data: old.data.map((d) =>
                  d.id === id ? { ...d, status: 'pending', error_message: null } : d,
                ),
              }
            : old,
      );
      void queryClient.invalidateQueries({ queryKey: ['documents', chatbotId] });
      toast.success('Document queued for reprocessing');
    },

    onError: (err) => {
      toast.error(err instanceof ApiError ? err.message : 'Failed to reprocess document');
    },
  });
}
