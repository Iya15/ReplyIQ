'use client';

import { use } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { ChevronRight, Bot } from 'lucide-react';
import { Skeleton } from '@/components/ui/skeleton';
import { useChatbot } from '@/hooks/use-chatbots';

const TABS = [
  { label: 'Overview', href: '' },
  { label: 'Customize', href: '/customize' },
  { label: 'Embed', href: '/embed' },
  { label: 'Knowledge', href: '/knowledge' },
  { label: 'Conversations', href: '/conversations' },
  { label: 'Analytics', href: '/analytics' },
  { label: 'Settings', href: '/settings' },
];

interface LayoutProps {
  children: React.ReactNode;
  params: Promise<{ id: string }>;
}

export default function ChatbotLayout({ children, params }: LayoutProps) {
  const { id } = use(params);
  const pathname = usePathname();
  const { data: chatbot, isLoading } = useChatbot(id);

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="border-b bg-background px-6 pt-6 pb-0">
        <nav className="flex items-center gap-1.5 text-sm text-muted-foreground mb-4">
          <Link href="/chatbots" className="hover:text-foreground transition-colors">
            Chatbots
          </Link>
          <ChevronRight className="h-3.5 w-3.5" />
          {isLoading ? (
            <Skeleton className="h-4 w-28" />
          ) : (
            <span className="text-foreground font-medium">{chatbot?.name}</span>
          )}
        </nav>

        <div className="flex items-center gap-3 mb-4">
          <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 shrink-0">
            <Bot className="h-4.5 w-4.5 text-primary" />
          </div>
          {isLoading ? (
            <Skeleton className="h-6 w-40" />
          ) : (
            <h1 className="text-xl font-semibold tracking-tight">{chatbot?.name}</h1>
          )}
        </div>

        {/* Tabs */}
        <div className="flex gap-0 overflow-x-auto" role="tablist">
          {TABS.map((tab) => {
            const href = `/chatbots/${id}${tab.href}`;
            const isActive =
              tab.href === ''
                ? pathname === `/chatbots/${id}`
                : pathname.startsWith(href);

            return (
              <Link
                key={tab.label}
                href={href}
                role="tab"
                aria-selected={isActive}
                className={`px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${
                  isActive
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'
                }`}
              >
                {tab.label}
              </Link>
            );
          })}
        </div>
      </div>

      {/* Page content */}
      <div className="flex-1 overflow-y-auto">
        {children}
      </div>
    </div>
  );
}
