'use client';

import { useMutation, useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { billingApi, ApiError } from '@/lib/api';
import type { CheckoutSessionPayload } from '@replyiq/api-client';

const BILLING_KEY = ['billing', 'subscription'] as const;

export function useBillingSubscription() {
  return useQuery({
    queryKey: BILLING_KEY,
    queryFn:  () => billingApi.getSubscription(),
    staleTime: 60_000,
  });
}

export function useCheckoutSession() {
  return useMutation({
    mutationFn: (payload: CheckoutSessionPayload) => billingApi.createCheckoutSession(payload),
    onSuccess: (res) => {
      window.location.href = res.data.url;
    },
    onError: (err) => {
      toast.error(err instanceof ApiError ? err.message : 'Failed to start checkout');
    },
  });
}

export function usePortalSession() {
  return useMutation({
    mutationFn: () => billingApi.createPortalSession(),
    onSuccess: (res) => {
      window.location.href = res.data.url;
    },
    onError: (err) => {
      toast.error(err instanceof ApiError ? err.message : 'Failed to open billing portal');
    },
  });
}
