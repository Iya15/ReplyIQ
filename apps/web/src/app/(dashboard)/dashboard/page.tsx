'use client';

import Link from 'next/link';
import { Plus, Bot } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuthStore } from '@/lib/auth/auth-store';
import { OnboardingChecklist } from '@/components/dashboard/onboarding-checklist';

const STAT_CARDS = [
  { label: 'Total Chatbots' },
  { label: 'Total Conversations' },
  { label: 'Messages This Month' },
  { label: 'Avg. Response Time' },
];

export default function DashboardPage() {
  const user = useAuthStore((s) => s.user);
  const firstName = user?.name?.split(' ')[0] ?? null;

  return (
    <div className="p-6 space-y-6 max-w-6xl mx-auto">
      {/* Greeting */}
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">
          {firstName ? `Good to see you, ${firstName}` : 'Good to see you'}
        </h1>
        <p className="text-sm text-muted-foreground mt-1">
          Here&apos;s what&apos;s happening with your chatbots.
        </p>
      </div>

      {/* Onboarding checklist — dismissible, shown to new users */}
      <OnboardingChecklist />

      {/* Stat cards — skeleton until real data arrives in a later milestone */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {STAT_CARDS.map(({ label }) => (
          <Card key={label}>
            <CardHeader className="pb-2">
              <CardDescription>{label}</CardDescription>
            </CardHeader>
            <CardContent>
              <Skeleton className="h-8 w-24" aria-label={`Loading ${label}`} />
            </CardContent>
          </Card>
        ))}
      </div>

      {/* CTA — visible until the user has at least one chatbot */}
      <Card className="border-dashed">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Bot className="h-5 w-5 text-primary" aria-hidden />
            Create your first chatbot
          </CardTitle>
          <CardDescription>
            Set up a chatbot, train it on your content, and embed it anywhere in minutes.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Button asChild>
            <Link href="/chatbots/new">
              <Plus className="h-4 w-4 mr-1.5" aria-hidden />
              New Chatbot
            </Link>
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
