import { Suspense } from 'react';
import { describe, it, expect, vi } from 'vitest';
import { screen, act } from '@testing-library/react';
import { renderWithProviders } from './utils';
import ChatbotsPage from '@/app/(dashboard)/chatbots/page';
import KnowledgePage from '@/app/(dashboard)/chatbots/[id]/knowledge/page';
import ConversationsPage from '@/app/(dashboard)/chatbots/[id]/conversations/page';
import AnalyticsPage from '@/app/(dashboard)/chatbots/[id]/analytics/page';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn(), prefetch: vi.fn() }),
  usePathname: () => '/chatbots',
  useParams: () => ({ id: 'test-chatbot-id' }),
}));

vi.mock('@/hooks/use-chatbots', () => ({
  useChatbots: () => ({ data: [], isLoading: false }),
  useChatbot: () => ({ data: null, isLoading: true }),
  useCreateChatbot: () => ({ mutateAsync: vi.fn(), isPending: false }),
  useUpdateChatbot: () => ({ mutate: vi.fn(), isPending: false }),
  useDeleteChatbot: () => ({ mutate: vi.fn(), isPending: false }),
  useUpdateChatbotSettings: () => ({ mutateAsync: vi.fn(), isPending: false }),
}));

vi.mock('@/hooks/use-conversations', () => ({
  useConversations: () => ({ data: undefined, isLoading: false }),
  useConversation:  () => ({ data: undefined, isLoading: false }),
  useResolveConversation: () => ({ mutate: vi.fn(), isPending: false }),
}));

vi.mock('@/hooks/use-messages', () => ({
  useMessages: () => ({ data: undefined, isLoading: false }),
}));

vi.mock('@/lib/echo', () => ({
  getEcho: () => ({ join: vi.fn(() => ({ listen: vi.fn() })), leave: vi.fn() }),
  destroyEcho: vi.fn(),
}));

vi.mock('@/hooks/use-documents', () => ({
  useDocuments: () => ({ data: undefined, isLoading: false }),
  useUploadDocument: () => ({ mutateAsync: vi.fn(), isPending: false, progress: 0, reset: vi.fn() }),
  useAddTextDocument: () => ({ mutateAsync: vi.fn(), isPending: false, reset: vi.fn() }),
  useDeleteDocument: () => ({ mutate: vi.fn(), isPending: false }),
  useReprocessDocument: () => ({ mutate: vi.fn(), isPending: false }),
}));

// ── Chatbots list ──────────────────────────────────────────────────────────

describe('ChatbotsPage', () => {
  it('renders the page heading', () => {
    renderWithProviders(<ChatbotsPage />);
    // h1 specifically, not the empty-state h2
    expect(screen.getByRole('heading', { level: 1, name: /chatbots/i })).toBeInTheDocument();
  });

  it('renders at least one new chatbot button', () => {
    renderWithProviders(<ChatbotsPage />);
    // Both topbar and empty-state CTAs are present — just confirm at least one exists.
    const buttons = screen.getAllByRole('button', { name: /new chatbot/i });
    expect(buttons.length).toBeGreaterThanOrEqual(1);
  });

  it('shows empty state when there are no chatbots', () => {
    renderWithProviders(<ChatbotsPage />);
    expect(screen.getByText(/no chatbots yet/i)).toBeInTheDocument();
  });
});

// ── Knowledge page ─────────────────────────────────────────────────────────

describe('KnowledgePage', () => {
  // use(params) suspends until the Promise resolves. We need a Suspense boundary
  // and must await act() to flush microtasks before asserting.
  async function renderPage() {
    await act(async () => {
      renderWithProviders(
        <Suspense fallback={null}>
          <KnowledgePage params={Promise.resolve({ id: 'test-chatbot-id' })} />
        </Suspense>,
      );
    });
  }

  it('renders the knowledge base heading', async () => {
    await renderPage();
    expect(screen.getByRole('heading', { name: /knowledge base/i })).toBeInTheDocument();
  });

  it('renders the empty state when there are no documents', async () => {
    await renderPage();
    expect(screen.getByText(/no knowledge yet/i)).toBeInTheDocument();
  });

  it('renders the Add knowledge button', async () => {
    await renderPage();
    expect(screen.getByRole('button', { name: /add knowledge/i })).toBeInTheDocument();
  });
});

describe('ConversationsPage', () => {
  it('renders the empty conversation list', async () => {
    await act(async () => {
      renderWithProviders(
        <Suspense fallback={null}>
          <ConversationsPage params={Promise.resolve({ id: 'test-chatbot-id' })} />
        </Suspense>,
      );
    });
    expect(screen.getByPlaceholderText(/search visitor id/i)).toBeInTheDocument();
  });
});

describe('AnalyticsPage', () => {
  it('renders the analytics heading', () => {
    renderWithProviders(<AnalyticsPage />);
    expect(screen.getByRole('heading', { name: /analytics/i })).toBeInTheDocument();
  });
});
