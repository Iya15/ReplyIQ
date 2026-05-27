// ── Response envelope ─────────────────────────────────────────────────────────
// Mirrors Controller::ok(), Controller::paginated(), Controller::error()

export interface ApiMeta {
  request_id: string;
}

export interface ApiResponse<T> {
  data: T;
  meta: ApiMeta;
}

export interface PaginatedMeta extends ApiMeta {
  page: number;
  per_page: number;
  total: number;
  has_more: boolean;
}

export interface Paginated<T> {
  data: T[];
  meta: PaginatedMeta;
}

// Thrown by the client; instanceof-checkable in catch blocks.
export class ApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly status: number,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

// ── Auth ──────────────────────────────────────────────────────────────────────

// UserResource: id, email, name, avatar_url, email_verified_at
export interface User {
  id: string;
  email: string;
  name: string;
  avatar_url: string | null;
  email_verified_at: string | null; // ISO 8601
}

// OrganizationResource: id, name, slug, plan
export interface Organization {
  id: string;
  name: string;
  slug: string;
  plan: string;
}

export type MembershipRole = 'owner' | 'admin' | 'member';

// MeResource: user, current_organization, role
export interface Me {
  user: User;
  current_organization: Organization | null;
  role: MembershipRole | null;
}

// ── Chatbots ──────────────────────────────────────────────────────────────────

// ChatbotStatus enum — matches PHP: Draft='draft' | Active='active' | Paused='paused'
export type ChatbotStatus = 'draft' | 'active' | 'paused';

// ChatbotSettingsResource — 19 fields, nullability from migration
export interface ChatbotSettings {
  // Branding
  logo_url: string | null;         // nullable text
  avatar_url: string | null;       // nullable text
  primary_color: string;           // NOT NULL, default '#4F46E5'
  text_color: string;              // NOT NULL, default '#0F172A'
  font_family: string;             // NOT NULL, default 'Inter'
  // Behavior
  welcome_message: string;         // NOT NULL
  placeholder_text: string | null; // nullable, default 'Ask me anything...'
  ai_tone: 'professional' | 'friendly' | 'casual' | 'formal'; // Rule::in in PHP
  ai_persona: string | null;       // nullable, system prompt extension
  // Widget
  position: 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left'; // Rule::in
  theme: 'light' | 'dark' | 'auto';                                      // Rule::in
  show_branding: boolean;
  // AI Config
  model: 'gpt-4o-mini' | 'gpt-4o' | 'gpt-4-turbo'; // Rule::in in PHP
  temperature: number;             // decimal 0–2
  max_tokens: number;              // int, default 800
  similarity_threshold: number;    // decimal 0–1
  retrieval_k: number;             // int 1–20
  fallback_message: string;        // NOT NULL
  allowed_domains: string[];       // TEXT[] PostgreSQL array, default '{}'
}

// ChatbotResource: id, name, public_id, status, language, created_at, settings?
export interface Chatbot {
  id: string;
  name: string;
  public_id: string;
  status: ChatbotStatus;
  language: string;
  created_at: string; // ISO 8601
  settings?: ChatbotSettings; // whenLoaded — always present from our endpoints
}

export interface EmbedCode {
  embed_code: string; // HTML <script> snippet containing public_id
}

// ── Request payload types ─────────────────────────────────────────────────────
// Derived from FormRequest rules — only fields the backend accepts.

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;          // min:12 — RegisterRequest has no 'confirmed' rule
  organization_name: string;
}

export interface LoginPayload {
  email: string;
  password: string;
  remember?: boolean;
}

export interface ResetPasswordPayload {
  token: string;
  email: string;
  password: string;
  password_confirmation: string; // required: ResetPasswordRequest uses 'confirmed'
}

export interface StoreChatbotPayload {
  name: string;
  language?: string;
}

export interface UpdateChatbotPayload {
  name?: string;
  status?: ChatbotStatus;
  language?: string;
  // public_id and organization_id intentionally absent — immutable / server-set
}

// All fields optional (PATCH semantics); uses union literals from ChatbotSettings
// so callers get autocomplete on the enum-like string fields.
export type UpdateChatbotSettingsPayload = Partial<{
  logo_url: string | null;
  avatar_url: string | null;
  primary_color: string;
  text_color: string;
  font_family: string;
  welcome_message: string;
  placeholder_text: string | null;
  ai_tone: ChatbotSettings['ai_tone'];
  ai_persona: string | null;
  position: ChatbotSettings['position'];
  theme: ChatbotSettings['theme'];
  show_branding: boolean;
  model: ChatbotSettings['model'];
  temperature: number;
  max_tokens: number;
  similarity_threshold: number;
  retrieval_k: number;
  fallback_message: string;
  allowed_domains: string[];
}>;

export interface ListParams {
  page?: number;
  per_page?: number;
}

// ── Documents ─────────────────────────────────────────────────────────────────

export type DocumentStatus = 'pending' | 'processing' | 'ready' | 'failed';
export type DocumentSourceType = 'pdf' | 'docx' | 'txt' | 'manual' | 'faq' | 'url';

export interface Document {
  id: string;
  title: string;
  source_type: DocumentSourceType;
  /** Only set for source_type === 'url' */
  source_url: string | null;
  status: DocumentStatus;
  error_message: string | null;
  char_count: number | null;
  chunk_count: number | null;
  /** URL docs include page_count, crawled_urls, max_pages after crawl completes */
  metadata: Record<string, unknown> | null;
  created_at: string; // ISO 8601
  processed_at: string | null;
}

export interface DocumentFilters {
  status?: DocumentStatus;
}

export interface StoreDocumentTextPayload {
  title: string;
  content: string;
}

export interface StoreDocumentUrlPayload {
  url: string;
  max_pages?: number; // 1–50 (free tier); default is 50 when omitted
}
