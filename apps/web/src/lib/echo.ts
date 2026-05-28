/**
 * Laravel Echo singleton for the dashboard.
 *
 * Connects to Laravel Reverb via the Pusher-compatible WebSocket protocol.
 * Dashboard users authenticate presence channels via Sanctum Bearer token
 * against the standard /broadcasting/auth endpoint.
 *
 * Usage:
 *   import { getEcho, destroyEcho } from '@/lib/echo';
 *
 *   const channel = getEcho(token).join(`chat.${conversationId}`);
 *   channel.listenForWhisper('message.token', (e) => { ... });
 *
 *   // On logout:
 *   destroyEcho();
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Pusher must be on window so laravel-echo can reference it.
if (typeof window !== 'undefined') {
  (window as typeof window & { Pusher: typeof Pusher }).Pusher = Pusher;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
let echoInstance: Echo<any> | null = null;

/**
 * Returns the shared Echo instance, creating it if needed.
 *
 * @param token  Sanctum Bearer token for the authenticated dashboard user.
 *               Pass `null` only if you need the instance before auth (rare).
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function getEcho(token: string | null): Echo<any> {
  if (echoInstance) return echoInstance;

  const scheme = process.env.NEXT_PUBLIC_REVERB_SCHEME ?? 'http';
  const port   = Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 8080);
  const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api/v1';
  // Auth endpoint lives at the API root, not under /api/v1.
  const authEndpoint = apiUrl.replace(/\/api\/v1\/?$/, '') + '/broadcasting/auth';

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  echoInstance = new Echo<any>({
    broadcaster:       'reverb',
    key:               process.env.NEXT_PUBLIC_REVERB_APP_KEY ?? '',
    wsHost:            process.env.NEXT_PUBLIC_REVERB_HOST ?? 'localhost',
    wsPort:            port,
    wssPort:           port,
    forceTLS:          scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint,
    auth: {
      headers: {
        Authorization: token ? `Bearer ${token}` : '',
        Accept:        'application/json',
      },
    },
  });

  return echoInstance;
}

/**
 * Disconnect and discard the Echo instance (call on logout or teardown).
 */
export function destroyEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect();
    echoInstance = null;
  }
}
