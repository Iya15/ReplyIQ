import type { ChatbotConfig, Message } from './types';

const BASE = __WIDGET_API_URL__;

export class RateLimitError extends Error {
  constructor(public readonly retryAfter: number) {
    super('rate_limited');
    this.name = 'RateLimitError';
  }
}

function authHeaders(token: string): Record<string, string> {
  return { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' };
}

export async function fetchConfig(chatbotId: string): Promise<ChatbotConfig> {
  const res = await fetch(`${BASE}/public/chatbots/${encodeURIComponent(chatbotId)}/config`);
  if (!res.ok) throw new Error(`config fetch failed: ${res.status}`);
  return ((await res.json()) as { data: ChatbotConfig }).data;
}

export async function getMessages(convId: string, token: string): Promise<Message[]> {
  const res = await fetch(`${BASE}/public/conversations/${convId}/messages`, {
    headers: authHeaders(token),
  });
  if (!res.ok) throw new Error(`messages fetch failed: ${res.status}`);
  return ((await res.json()) as { data: Message[] }).data;
}

export async function sendMessage(
  convId: string,
  token: string,
  content: string,
): Promise<{ user_message: Message; assistant_message: Message | null }> {
  const res = await fetch(`${BASE}/public/conversations/${convId}/messages`, {
    method:  'POST',
    headers: authHeaders(token),
    body:    JSON.stringify({ content }),
  });
  if (res.status === 429) {
    const retryAfter = parseInt(res.headers.get('Retry-After') ?? '60', 10);
    throw new RateLimitError(retryAfter);
  }
  if (!res.ok) throw new Error(`send failed: ${res.status}`);
  return ((await res.json()) as { data: { user_message: Message; assistant_message: Message | null } }).data;
}

export async function requestHuman(convId: string, token: string): Promise<void> {
  await fetch(`${BASE}/public/conversations/${convId}/request-human`, {
    method:  'POST',
    headers: authHeaders(token),
  });
}

export async function sendFeedback(
  msgId: string,
  token: string,
  feedback: 'helpful' | 'not_helpful',
): Promise<void> {
  await fetch(`${BASE}/public/messages/${msgId}/feedback`, {
    method:  'POST',
    headers: authHeaders(token),
    body:    JSON.stringify({ feedback }),
  });
}
