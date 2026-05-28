import type { ApiClient } from '../client';
import type {
  ApiResponse,
  BillingSubscription,
  CheckoutSessionPayload,
} from '../types';

export function createBillingEndpoints(client: ApiClient) {
  return {
    // GET /billing/subscription
    getSubscription: () =>
      client.get<ApiResponse<BillingSubscription>>('/billing/subscription'),

    // POST /billing/checkout-session → { url: string }
    createCheckoutSession: (payload: CheckoutSessionPayload) =>
      client.post<ApiResponse<{ url: string }>>('/billing/checkout-session', payload),

    // POST /billing/portal-session → { url: string }
    createPortalSession: () =>
      client.post<ApiResponse<{ url: string }>>('/billing/portal-session', {}),
  };
}
