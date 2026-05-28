// Types — import with `import type` in consuming code for best tree-shaking
export type {
  ApiMeta,
  ApiResponse,
  PaginatedMeta,
  Paginated,
  User,
  Organization,
  MembershipRole,
  Me,
  ChatbotStatus,
  ChatbotSettings,
  Chatbot,
  EmbedCode,
  RegisterPayload,
  LoginPayload,
  ResetPasswordPayload,
  StoreChatbotPayload,
  UpdateChatbotPayload,
  UpdateChatbotSettingsPayload,
  ListParams,
  DocumentStatus,
  DocumentSourceType,
  Document,
  DocumentFilters,
  StoreDocumentTextPayload,
  StoreDocumentUrlPayload,
  ConversationStatus,
  ConversationLastMessage,
  Conversation,
  MessageSource,
  Message,
  ConversationFilters,
} from './types';

// ApiError is a class — runtime value, importable without `type`
export { ApiError } from './types';

// Client factory
export { createApiClient } from './client';
export type { ApiClient, ApiClientConfig } from './client';

// Endpoint factories
export { createAuthEndpoints } from './endpoints/auth';
export { createChatbotEndpoints } from './endpoints/chatbots';
export { createDocumentEndpoints } from './endpoints/documents';
export { createConversationEndpoints } from './endpoints/conversations';
