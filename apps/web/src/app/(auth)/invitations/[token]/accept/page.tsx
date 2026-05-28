'use client';

import { use, useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { teamApi, ApiError } from '@/lib/api';
import { useAuthStore } from '@/lib/auth/auth-store';
import type { InvitationPreview } from '@replyiq/api-client';

const newUserSchema = z.object({
  name:     z.string().min(2, 'Name is required'),
  password: z.string().min(12, 'Password must be at least 12 characters'),
});

type NewUserForm = z.infer<typeof newUserSchema>;

interface PageProps {
  params: Promise<{ token: string }>;
}

export default function AcceptInvitationPage({ params }: PageProps) {
  const { token }     = use(params);
  const router        = useRouter();
  const loginStore    = useAuthStore((s) => s.login);

  const [preview, setPreview]   = useState<InvitationPreview | null>(null);
  const [error, setError]       = useState<string | null>(null);
  const [loading, setLoading]   = useState(true);
  const [isNewUser, setIsNewUser] = useState(false);
  const [accepting, setAccepting] = useState(false);

  const form = useForm<NewUserForm>({ resolver: zodResolver(newUserSchema) });

  // Fetch invitation preview
  useEffect(() => {
    teamApi.getInvitation(token)
      .then((res) => {
        setPreview(res.data);
        setLoading(false);
      })
      .catch((err) => {
        setError(err instanceof ApiError ? err.message : 'This invitation is invalid or has expired.');
        setLoading(false);
      });
  }, [token]);

  // Try accepting without credentials first (existing user path)
  useEffect(() => {
    if (!preview) return;
    teamApi.acceptInvitation(token, {})
      .then((res) => {
        loginStore(res.data.token, res.data.user);
        router.push('/team');
      })
      .catch(() => {
        // Not a registered user — show the name+password form.
        setIsNewUser(true);
      });
  }, [preview, token, loginStore, router]);

  async function onSubmit(data: NewUserForm) {
    setAccepting(true);
    try {
      const res = await teamApi.acceptInvitation(token, data);
      loginStore(res.data.token, res.data.user);
      router.push('/team');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to accept invitation.');
    } finally {
      setAccepting(false);
    }
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="h-8 w-48 animate-pulse rounded bg-muted" />
        <div className="h-4 w-64 animate-pulse rounded bg-muted" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-4 text-center">
        <h1 className="text-2xl font-semibold">Invitation not found</h1>
        <p className="text-sm text-muted-foreground">{error}</p>
        <Button variant="outline" onClick={() => router.push('/login')}>Go to sign in</Button>
      </div>
    );
  }

  if (!isNewUser) {
    return (
      <div className="space-y-4 text-center">
        <div className="h-8 w-40 animate-pulse rounded bg-muted mx-auto" />
        <p className="text-sm text-muted-foreground">Joining {preview?.organization_name}…</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="space-y-1">
        <h1 className="text-2xl font-semibold tracking-tight">
          Join {preview?.organization_name}
        </h1>
        <p className="text-sm text-muted-foreground">
          {preview?.invited_by
            ? `${preview.invited_by} invited you to join as a ${preview.role}.`
            : `You've been invited to join as a ${preview?.role}.`}
        </p>
        <p className="text-xs text-muted-foreground">
          Your account will be created for <strong>{preview?.email}</strong>.
        </p>
      </div>

      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor="name">Your name</Label>
          <Input
            id="name"
            placeholder="Jane Smith"
            {...form.register('name')}
            autoFocus
          />
          {form.formState.errors.name && (
            <p className="text-xs text-destructive">{form.formState.errors.name.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="password">Create a password</Label>
          <Input
            id="password"
            type="password"
            placeholder="Min 12 characters"
            {...form.register('password')}
          />
          {form.formState.errors.password && (
            <p className="text-xs text-destructive">{form.formState.errors.password.message}</p>
          )}
        </div>

        {error && <p className="text-xs text-destructive">{error}</p>}

        <Button type="submit" className="w-full" disabled={accepting}>
          {accepting ? 'Joining…' : `Join ${preview?.organization_name}`}
        </Button>
      </form>

      <p className="text-xs text-center text-muted-foreground">
        Already have an account?{' '}
        <a href="/login" className="underline hover:text-foreground">Sign in</a>
      </p>
    </div>
  );
}
