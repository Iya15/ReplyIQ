'use client';

import Link from 'next/link';
import { Skeleton } from '@/components/ui/skeleton';
import { Button } from '@/components/ui/button';
import { useBillingSubscription, useCheckoutSession, usePortalSession } from '@/hooks/use-billing';
import type { PlanSlug, UsageMetric } from '@replyiq/api-client';

const PLAN_LABELS: Record<PlanSlug, string> = {
  free:     'Free',
  starter:  'Starter',
  pro:      'Pro',
  business: 'Business',
};

const PLAN_COLORS: Record<PlanSlug, string> = {
  free:     'bg-muted text-muted-foreground',
  starter:  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  pro:      'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
  business: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
};

const UPGRADE_PRICES: Record<PlanSlug, string | null> = {
  free:     process.env.NEXT_PUBLIC_STRIPE_PRICE_STARTER_MONTHLY ?? null,
  starter:  process.env.NEXT_PUBLIC_STRIPE_PRICE_PRO_MONTHLY ?? null,
  pro:      process.env.NEXT_PUBLIC_STRIPE_PRICE_BUSINESS_MONTHLY ?? null,
  business: null,
};

// ── Usage bar ─────────────────────────────────────────────────────────────────

function UsageBar({ label, metric }: { label: string; metric: UsageMetric }) {
  const unlimited = metric.limit === -1;
  const pct = unlimited ? 0 : Math.min(100, Math.round((metric.current / metric.limit) * 100));
  const warn = pct >= 80;

  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between text-sm">
        <span className="text-muted-foreground">{label}</span>
        <span className={`font-medium tabular-nums ${warn ? 'text-amber-600' : ''}`}>
          {metric.current.toLocaleString()}
          {unlimited ? '' : ` / ${metric.limit.toLocaleString()}`}
        </span>
      </div>
      {!unlimited && (
        <div className="h-2 rounded-full bg-muted overflow-hidden">
          <div
            className={`h-full rounded-full transition-all ${warn ? 'bg-amber-500' : 'bg-primary'}`}
            style={{ width: `${pct}%` }}
          />
        </div>
      )}
      {unlimited && (
        <p className="text-xs text-muted-foreground">Unlimited</p>
      )}
    </div>
  );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function BillingPage() {
  const { data, isLoading }     = useBillingSubscription();
  const checkout                = useCheckoutSession();
  const portal                  = usePortalSession();

  const billing    = data?.data;
  const plan        = billing?.plan ?? 'free';
  const upgradePrice = UPGRADE_PRICES[plan];

  return (
    <div className="p-6 max-w-2xl mx-auto space-y-8">
      {/* Header */}
      <div>
        <h1 className="text-xl font-semibold">Billing</h1>
        <p className="text-sm text-muted-foreground mt-0.5">
          Manage your subscription and monitor resource usage.
        </p>
      </div>

      {/* Current plan card */}
      <div className="rounded-xl border bg-card p-6 space-y-4">
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div className="space-y-1">
            <p className="text-sm text-muted-foreground">Current plan</p>
            {isLoading ? (
              <Skeleton className="h-7 w-28" />
            ) : (
              <div className="flex items-center gap-2">
                <span className="text-2xl font-bold">{PLAN_LABELS[plan]}</span>
                <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${PLAN_COLORS[plan]}`}>
                  {billing?.subscription?.stripe_status ?? 'free tier'}
                </span>
              </div>
            )}
            {billing?.subscription?.cancel_at_period_end && (
              <p className="text-xs text-amber-600">Cancels at end of billing period.</p>
            )}
          </div>
          <div className="flex gap-2 flex-wrap">
            {billing?.subscription !== null && billing?.subscription !== undefined && (
              <Button variant="outline" disabled={portal.isPending} onClick={() => portal.mutate()}>
                {portal.isPending ? 'Opening…' : 'Manage subscription'}
              </Button>
            )}
            {upgradePrice && (
              <Button disabled={checkout.isPending} onClick={() => checkout.mutate({ price_id: upgradePrice })}>
                {checkout.isPending ? 'Redirecting…' : 'Upgrade plan'}
              </Button>
            )}
            {plan === 'free' && !upgradePrice && (
              <Button asChild>
                <Link href="/pricing">View plans</Link>
              </Button>
            )}
          </div>
        </div>
      </div>

      {/* Usage */}
      <div className="rounded-xl border bg-card p-6 space-y-5">
        <h2 className="text-sm font-semibold">Usage this month</h2>
        {isLoading || !billing ? (
          <div className="space-y-4">
            {Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-8 w-full" />)}
          </div>
        ) : (
          <>
            <UsageBar label="Chatbots"         metric={billing.usage.chatbots} />
            <UsageBar label="Messages"         metric={billing.usage.messages_per_month} />
            <UsageBar label="Documents"        metric={billing.usage.documents} />
            <UsageBar label="Team members"     metric={billing.usage.team_size} />
          </>
        )}
      </div>

      {plan === 'free' && (
        <p className="text-sm text-center text-muted-foreground">
          Need more?{' '}
          <Link href="/pricing" className="underline hover:text-foreground">
            Compare plans
          </Link>
        </p>
      )}
    </div>
  );
}
