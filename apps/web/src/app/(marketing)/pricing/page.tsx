import type { Metadata } from 'next';
import Link from 'next/link';
import { Check } from 'lucide-react';

export const metadata: Metadata = { title: 'Pricing' };

const PLANS = [
  {
    name:        'Free',
    price:       '$0',
    period:      'forever',
    description: 'Perfect for trying ReplyIQ.',
    cta:         'Get started',
    ctaHref:     '/register',
    highlight:   false,
    features: [
      '1 chatbot',
      '500 messages / month',
      '10 documents',
      '1 team member',
      'GPT-4o Mini',
      'Community support',
    ],
  },
  {
    name:        'Starter',
    price:       '$29',
    period:      '/month',
    description: 'For small teams getting started.',
    cta:         'Start free trial',
    ctaHref:     '/register?plan=starter',
    highlight:   false,
    features: [
      '5 chatbots',
      '5,000 messages / month',
      '100 documents',
      '3 team members',
      'GPT-4o Mini + GPT-4o',
      'Email support',
    ],
  },
  {
    name:        'Pro',
    price:       '$89',
    period:      '/month',
    description: 'For growing businesses.',
    cta:         'Start free trial',
    ctaHref:     '/register?plan=pro',
    highlight:   true,
    features: [
      '20 chatbots',
      '25,000 messages / month',
      '500 documents',
      '10 team members',
      'All GPT models',
      'Priority support',
    ],
  },
  {
    name:        'Business',
    price:       '$249',
    period:      '/month',
    description: 'For large-scale deployments.',
    cta:         'Contact us',
    ctaHref:     '/register?plan=business',
    highlight:   false,
    features: [
      'Unlimited chatbots',
      'Unlimited messages',
      'Unlimited documents',
      'Unlimited team members',
      'All GPT models',
      'Dedicated support',
    ],
  },
] as const;

export default function PricingPage() {
  return (
    <div className="py-24 px-6">
      <div className="mx-auto max-w-6xl space-y-12">
        {/* Header */}
        <div className="text-center space-y-3 max-w-2xl mx-auto">
          <h1 className="text-4xl font-bold tracking-tight">Simple, transparent pricing</h1>
          <p className="text-lg text-muted-foreground">
            Start free. Upgrade as you grow. Cancel anytime.
          </p>
        </div>

        {/* Plan cards */}
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
          {PLANS.map((plan) => (
            <div
              key={plan.name}
              className={`relative rounded-2xl border p-6 flex flex-col gap-6 ${
                plan.highlight
                  ? 'border-primary shadow-lg ring-1 ring-primary'
                  : 'bg-card'
              }`}
            >
              {plan.highlight && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-primary px-3 py-0.5 text-xs font-semibold text-primary-foreground">
                  Most popular
                </div>
              )}

              <div className="space-y-2">
                <h2 className="text-lg font-semibold">{plan.name}</h2>
                <div className="flex items-baseline gap-1">
                  <span className="text-3xl font-bold">{plan.price}</span>
                  <span className="text-sm text-muted-foreground">{plan.period}</span>
                </div>
                <p className="text-sm text-muted-foreground">{plan.description}</p>
              </div>

              <Link
                href={plan.ctaHref}
                className={`block text-center rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors ${
                  plan.highlight
                    ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                    : 'border border-input bg-background hover:bg-muted'
                }`}
              >
                {plan.cta}
              </Link>

              <ul className="space-y-2.5 text-sm">
                {plan.features.map((f) => (
                  <li key={f} className="flex items-center gap-2.5">
                    <Check className="h-4 w-4 shrink-0 text-green-500" />
                    <span>{f}</span>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        <p className="text-center text-sm text-muted-foreground">
          All plans include SSL, uptime SLA, and GDPR compliance.{' '}
          <Link href="/login" className="underline hover:text-foreground">
            Already have an account?
          </Link>
        </p>
      </div>
    </div>
  );
}
