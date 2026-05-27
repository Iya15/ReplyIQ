import { describe, it, expect, vi, beforeEach } from 'vitest';
import { screen } from '@testing-library/react';
import { renderWithProviders } from './utils';
import { LoginForm } from '@/components/auth/login-form';
import { RegisterForm } from '@/components/auth/register-form';
import { ForgotPasswordForm } from '@/components/auth/forgot-password-form';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn(), prefetch: vi.fn() }),
  usePathname: () => '/',
}));

vi.mock('@/hooks/use-auth', () => ({
  useLogin: () => ({ mutateAsync: vi.fn(), isPending: false }),
  useRegister: () => ({ mutateAsync: vi.fn(), isPending: false }),
  ApiError: class ApiError extends Error {
    status: number;
    constructor(code: string, message: string, status: number) {
      super(message);
      this.status = status;
    }
  },
}));

vi.mock('@/lib/api', () => ({
  authApi: {
    forgotPassword: vi.fn(),
    login: vi.fn(),
    register: vi.fn(),
  },
  ApiError: class ApiError extends Error {
    status: number;
    constructor(code: string, message: string, status: number) {
      super(message);
      this.status = status;
    }
  },
}));

// ── Login page ─────────────────────────────────────────────────────────────

describe('LoginForm', () => {
  it('renders email and password fields', () => {
    renderWithProviders(<LoginForm />);
    expect(screen.getByLabelText(/email/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/password/i)).toBeInTheDocument();
  });

  it('renders the sign-in submit button', () => {
    renderWithProviders(<LoginForm />);
    expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument();
  });

  it('renders the forgot password link', () => {
    renderWithProviders(<LoginForm />);
    expect(screen.getByRole('link', { name: /forgot/i })).toBeInTheDocument();
  });
});

// ── Register page ──────────────────────────────────────────────────────────

describe('RegisterForm', () => {
  it('renders all registration fields', () => {
    renderWithProviders(<RegisterForm />);
    expect(screen.getByLabelText(/full name/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/email/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/password/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/organization/i)).toBeInTheDocument();
  });

  it('renders the create account button', () => {
    renderWithProviders(<RegisterForm />);
    expect(screen.getByRole('button', { name: /create account/i })).toBeInTheDocument();
  });
});

// ── Forgot password page ───────────────────────────────────────────────────

describe('ForgotPasswordForm', () => {
  it('renders the email input and submit button', () => {
    renderWithProviders(<ForgotPasswordForm />);
    expect(screen.getByLabelText(/email address/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /send reset link/i })).toBeInTheDocument();
  });

  it('renders a link back to sign in', () => {
    renderWithProviders(<ForgotPasswordForm />);
    expect(screen.getByRole('link', { name: /sign in/i })).toBeInTheDocument();
  });
});
