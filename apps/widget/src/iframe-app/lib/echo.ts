import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import type { Session } from './types';

// pusher-js must be on window for the reverb broadcaster to pick it up
(window as unknown as Record<string, unknown>)['Pusher'] = Pusher;

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function createEcho(session: Session): Echo<any> {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  return new Echo<any>({
    broadcaster:       'reverb',
    key:               __REVERB_APP_KEY__,
    wsHost:            __REVERB_HOST__,
    wsPort:            __REVERB_PORT__,
    wssPort:           __REVERB_PORT__,
    forceTLS:          __REVERB_SCHEME__ === 'wss',
    enabledTransports: ['ws', 'wss'],
    authEndpoint:      `${session.apiBase}/public/broadcasting/auth`,
    auth: {
      headers: { Authorization: `Bearer ${session.token}` },
    },
  });
}
