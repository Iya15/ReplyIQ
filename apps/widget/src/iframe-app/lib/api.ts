import type { ChatbotConfig, Message } from './types';

const BASE = __WIDGET_API_URL__;

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
): Promise<{ user_message: Message; assistant_message: Message }> {
  const res = await fetch(`${BASE}/public/conversations/${convId}/messages`, {
    method:  'POST',
    headers: authHeaders(token),
    body:    JSON.stringify({ content }),
  });
  if (!res.ok) throw new Error(`send failed: ${res.status}`);
  return ((await res.json()) as { data: { user_message: Message; assistant_message: Message } }).data;
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
