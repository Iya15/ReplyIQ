import { renderHook, act } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { useConversation } from './useConversation';

// ── Mocks ──────────────────────────────────────────────────────────────────────

type EventCb      = (data: Record<string, unknown>) => void;
type ConnectionCb = () => void;

interface MockChannel {
  listen:  ReturnType<typeof vi.fn>;
  here:    ReturnType<typeof vi.fn>;
  joining: ReturnType<typeof vi.fn>;
  leaving: ReturnType<typeof vi.fn>;
  _emit:   (event: string, data: Record<string, unknown>) => void;
}

interface MockEcho {
  join:              ReturnType<typeof vi.fn>;
  leave:             ReturnType<typeof vi.fn>;
  disconnect:        ReturnType<typeof vi.fn>;
  connector:         { pusher: { connection: { bind: ReturnType<typeof vi.fn> } } };
  _triggerConnected: () => void;
}

function buildMocks() {
  const eventListeners: Record<string, EventCb>      = {};
  const connListeners:  Record<string, ConnectionCb> = {};

  const channel: MockChannel = {
    listen:  vi.fn().mockImplementation((ev: string, cb: EventCb) => {
      eventListeners[ev] = cb;
      return channel;
    }),
    here:    vi.fn().mockReturnThis(),
    joining: vi.fn().mockReturnThis(),
    leaving: vi.fn().mockReturnThis(),
    _emit:   (ev, data) => eventListeners[ev]?.(data),
  };

  const echo: MockEcho = {
    join:       vi.fn().mockReturnValue(channel),
    leave:      vi.fn(),
    disconnect: vi.fn(),
    connector:  {
      pusher: {
        connection: {
          bind: vi.fn().mockImplementation((ev: string, cb: ConnectionCb) => {
            connListeners[ev] = cb;
          }),
        },
      },
    },
    _triggerConnected: () => connListeners['connected']?.(),
  };

  return { echo, channel };
}

let mocks = buildMocks();

vi.mock('../lib/echo', () => ({
  createEcho: vi.fn(() => mocks.echo),
}));

vi.mock('../lib/api', () => ({
  getMessages:  vi.fn().mockResolvedValue([]),
  sendMessage:  vi.fn(),
  sendFeedback: vi.fn(),
}));

import { getMessages, sendMessage as apiSendMessage } from '../lib/api';

// ── Helpers ────────────────────────────────────────────────────────────────────

const SESSION = {
  token:          'tok-abc',
  conversationId: 'conv-123',
  visitorId:      'vis-xyz',
  apiBase:        'https://api.replyiq.com/api/v1',
};

// Flush all pending microtasks (resolved-promise continuations).
const flushMicrotasks = () => act(async () => {});

function makeMsg(id: string, role: 'user' | 'assistant', status: 'pending' | 'complete' = 'complete') {
  return { id, role, content: role === 'user' ? 'Hello' : '', status, sources: [], confidence: null, created_at: new Date().toISOString() };
}

// ── Setup ──────────────────────────────────────────────────────────────────────

beforeEach(() => {
  mocks = buildMocks();
  vi.mocked(getMessages).mockResolvedValue([]);
  vi.useFakeTimers();
});

afterEach(() => {
  vi.runAllTimers();
  vi.useRealTimers();
  vi.clearAllMocks();
});

// ── Tests ──────────────────────────────────────────────────────────────────────

describe('useConversation — initial load', () => {
  it('fetches messages when session becomes available', async () => {
    const msg = { ...makeMsg('m1', 'assistant'), content: 'Hi', status: 'complete' as const };
    vi.mocked(getMessages).mockResolvedValueOnce([msg]);

    const { result } = renderHook(() => useConversation(SESSION));
    await flushMicrotasks(); // settle getMessages promise chain

    expect(result.current.messages).toHaveLength(1);
    expect(result.current.messages[0]?.id).toBe('m1');
  });

  it('subscribes to the presence channel on mount', async () => {
    renderHook(() => useConversation(SESSION));
    await flushMicrotasks();

    expect(mocks.echo.join).toHaveBeenCalledWith('presence-chat.conv-123');
  });
});

describe('useConversation — WebSocket streaming', () => {
  it('appends tokens to the pending assistant message', async () => {
    const assistId = 'asst-1';
    vi.mocked(apiSendMessage).mockResolvedValue({
      user_message:      makeMsg('user-1', 'user'),
      assistant_message: makeMsg(assistId, 'assistant', 'pending'),
    });

    const { result } = renderHook(() => useConversation(SESSION));
    await flushMicrotasks();

    await act(async () => { await result.current.sendMessage('Hello'); });

    await act(async () => { mocks.channel._emit('message.token', { messageId: assistId, token: 'World' }); });
    await act(async () => { mocks.channel._emit('message.token', { messageId: assistId, token: '!' }); });

    const asst = result.current.messages.find(m => m.id === assistId);
    expect(asst?.content).toBe('World!');
    expect(result.current.isPending).toBe(true);
  });

  it('finalizes the message on message.completed', async () => {
    const assistId = 'asst-2';
    vi.mocked(apiSendMessage).mockResolvedValue({
      user_message:      makeMsg('user-2', 'user'),
      assistant_message: makeMsg(assistId, 'assistant', 'pending'),
    });

    const { result } = renderHook(() => useConversation(SESSION));
    await flushMicrotasks();
    await act(async () => { await result.current.sendMessage('Hi'); });

    await act(async () => {
      mocks.channel._emit('message.token',     { messageId: assistId, token: 'Done.' });
      mocks.channel._emit('message.completed', {
        messageId: assistId, content: 'Done.', status: 'complete', sources: [], confidence: 0.9,
      });
    });

    const asst = result.current.messages.find(m => m.id === assistId);
    expect(asst?.status).toBe('complete');
    expect(asst?.content).toBe('Done.');
    expect(asst?.confidence).toBe(0.9);
    expect(result.current.isPending).toBe(false);
  });
});

describe('useConversation — polling fallback', () => {
  it('falls back to polling when WS does not connect within 5 s', async () => {
    const assistId = 'asst-fb';
    vi.mocked(apiSendMessage).mockResolvedValue({
      user_message:      makeMsg('user-fb', 'user'),
      assistant_message: makeMsg(assistId, 'assistant', 'pending'),
    });

    const pollResponse = [{ ...makeMsg(assistId, 'assistant'), content: 'Fallback reply.', status: 'complete' as const }];
    vi.mocked(getMessages)
      .mockResolvedValueOnce([])     // initial load
      .mockResolvedValue(pollResponse);

    const { result } = renderHook(() => useConversation(SESSION));
    await flushMicrotasks();

    // Send message (WS never connects — fallback timer still pending)
    await act(async () => { await result.current.sendMessage('test'); });

    // Jump past the 5-second fallback window → starts poll interval
    await act(async () => { vi.advanceTimersByTime(5001); });
    await flushMicrotasks();

    // Fire one poll tick → getMessages returns complete message
    await act(async () => { vi.advanceTimersByTime(1500); });
    await flushMicrotasks(); // settle the async poll callback

    expect(result.current.isPending).toBe(false);
    expect(result.current.messages.find(m => m.id === assistId)?.status).toBe('complete');
  });
});

describe('useConversation — cleanup', () => {
  it('leaves the channel and disconnects Echo on unmount', async () => {
    const { unmount } = renderHook(() => useConversation(SESSION));
    await flushMicrotasks();

    unmount();

    expect(mocks.echo.leave).toHaveBeenCalledWith('presence-chat.conv-123');
    expect(mocks.echo.disconnect).toHaveBeenCalled();
  });
});
