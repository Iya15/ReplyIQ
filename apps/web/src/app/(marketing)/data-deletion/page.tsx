'use client';

import { useState } from 'react';
import type { Metadata } from 'next';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

export default function DataDeletionPage() {
  const [email, setEmail]     = useState('');
  const [status, setStatus]   = useState<'idle' | 'loading' | 'done' | 'error'>('idle');

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setStatus('loading');

    try {
      const res = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL?.replace('/api/v1', '') ?? ''}/api/v1/gdpr/data-deletion`,
        {
          method:  'POST',
          headers: { 'Content-Type': 'application/json' },
          body:    JSON.stringify({ email }),
        },
      );
      setStatus(res.ok ? 'done' : 'error');
    } catch {
      setStatus('error');
    }
  }

  return (
    <div className="py-16 px-6">
      <div className="mx-auto max-w-xl space-y-8">
        <div className="space-y-2">
          <h1 className="text-3xl font-bold tracking-tight">Data Deletion Request</h1>
          <p className="text-muted-foreground">
            Submit your email address and we will delete all personal data associated with your
            account within 30 days, as required by GDPR Article 17.
          </p>
        </div>

        {status === 'done' ? (
          <div className="rounded-xl border border-green-200 bg-green-50 dark:bg-green-950/20 p-6 space-y-2">
            <p className="font-semibold text-green-800 dark:text-green-300">Request received</p>
            <p className="text-sm text-green-700 dark:text-green-400">
              We will process your data deletion request within 30 days and send a confirmation to{' '}
              <strong>{email}</strong>.
            </p>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="space-y-1.5">
              <Label htmlFor="del-email">Email address</Label>
              <Input
                id="del-email"
                type="email"
                placeholder="you@example.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                autoFocus
              />
            </div>

            {status === 'error' && (
              <p className="text-sm text-destructive">
                Something went wrong. Please try again or email{' '}
                <a href="mailto:privacy@replyiq.com" className="underline">
                  privacy@replyiq.com
                </a>{' '}
                directly.
              </p>
            )}

            <Button type="submit" disabled={status === 'loading'}>
              {status === 'loading' ? 'Submitting…' : 'Submit deletion request'}
            </Button>
          </form>
        )}

        <p className="text-xs text-muted-foreground">
          Alternatively, email us at{' '}
          <a href="mailto:privacy@replyiq.com" className="underline hover:text-foreground">
            privacy@replyiq.com
          </a>
          . See our <a href="/privacy" className="underline hover:text-foreground">Privacy Policy</a>{' '}
          for details on data retention.
        </p>
      </div>
    </div>
  );
}
