'use client';

import { usePathname } from 'next/navigation';
import { useTheme } from 'next-themes';
import { Moon, Sun, Bell, Command, Menu } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useAppStore } from '@/stores';
import { cn } from '@/lib/utils';

const ROUTE_LABELS: Record<string, string> = {
  dashboard: 'Overview',
  chatbots: 'Chatbots',
  conversations: 'Conversations',
  analytics: 'Analytics',
  team: 'Team',
  settings: 'Settings',
};

function useBreadcrumbs(): Array<{ label: string; href: string; isLast: boolean }> {
  const pathname = usePathname();
  const segments = pathname.split('/').filter(Boolean);

  return segments.map((seg, i) => ({
    label: ROUTE_LABELS[seg] ?? seg.charAt(0).toUpperCase() + seg.slice(1).replace(/-/g, ' '),
    href: '/' + segments.slice(0, i + 1).join('/'),
    isLast: i === segments.length - 1,
  }));
}

export function Topbar() {
  const breadcrumbs = useBreadcrumbs();
  const { theme, setTheme } = useTheme();
  const { toggleSidebar } = useAppStore();

  return (
    <header className="flex h-14 flex-shrink-0 items-center border-b border-border bg-background px-4 gap-3">
      {/* Mobile sidebar toggle */}
      <button
        onClick={toggleSidebar}
        className="lg:hidden flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
        aria-label="Toggle navigation"
      >
        <Menu className="h-4 w-4" aria-hidden />
      </button>

      {/* Breadcrumbs */}
      <nav aria-label="Breadcrumb" className="flex items-center gap-1.5 text-sm min-w-0">
        {breadcrumbs.map((crumb, i) => (
          <span key={crumb.href} className="flex items-center gap-1.5 min-w-0">
            {i > 0 && (
              <span className="text-muted-foreground/50 flex-shrink-0" aria-hidden>
                /
              </span>
            )}
            <span
              className={cn(
                'truncate',
                crumb.isLast
                  ? 'font-medium text-foreground'
                  : 'text-muted-foreground hover:text-foreground',
              )}
              aria-current={crumb.isLast ? 'page' : undefined}
            >
              {crumb.label}
            </span>
          </span>
        ))}
      </nav>

      <div className="ml-auto flex items-center gap-1">
        {/* Command palette trigger (placeholder) */}
        <button
          className="hidden md:flex items-center gap-1.5 h-7 rounded-md border border-border px-2 text-xs text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
          aria-label="Open command palette"
          disabled
        >
          <Command className="h-3 w-3" aria-hidden />
          <span>K</span>
        </button>

        <Separator orientation="vertical" className="h-5 mx-1 hidden md:block" />

        {/* Notification bell (placeholder) */}
        <Button
          variant="ghost"
          size="icon"
          className="h-8 w-8 text-muted-foreground"
          aria-label="Notifications"
          disabled
        >
          <Bell className="h-4 w-4" aria-hidden />
        </Button>

        {/* Theme toggle */}
        <Button
          variant="ghost"
          size="icon"
          className="h-8 w-8 text-muted-foreground"
          aria-label={theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
          onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
        >
          <Sun className="h-4 w-4 rotate-0 scale-100 transition-transform dark:-rotate-90 dark:scale-0" aria-hidden />
          <Moon className="absolute h-4 w-4 rotate-90 scale-0 transition-transform dark:rotate-0 dark:scale-100" aria-hidden />
        </Button>
      </div>
    </header>
  );
}
