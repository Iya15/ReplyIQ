import { describe, it, expect, vi } from 'vitest';
import { screen } from '@testing-library/react';
import { renderWithProviders } from './utils';
import DashboardPage from '@/app/(dashboard)/dashboard/page';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn(), prefetch: vi.fn() }),
  usePathname: () => '/dashboard',
  useParams: () => ({}),
}));

vi.mock('@/lib/auth/auth-store', () => ({
  useAuthStore: (selector: (s: { user: null }) => unknown) =>
    selector({ user: null }),
}));

// ── Dashboard overview ─────────────────────────────────────────────────────

describe('DashboardPage', () => {
  it('renders a greeting heading', () => {
    renderWithProviders(<DashboardPage />);
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
  });

  it('renders the stat cards section', () => {
    renderWithProviders(<DashboardPage />);
    expect(screen.getByText(/total chatbots/i)).toBeInTheDocument();
  });

  it('renders the new chatbot CTA', () => {
    renderWithProviders(<DashboardPage />);
    expect(screen.getByRole('link', { name: /new chatbot/i })).toBeInTheDocument();
  });
});
