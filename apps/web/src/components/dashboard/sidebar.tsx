'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  LayoutDashboard,
  Bot,
  MessageSquare,
  BarChart3,
  Users,
  Settings,
  ChevronLeft,
  ChevronRight,
  Building2,
  LogOut,
} from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { useAuthStore } from '@/lib/auth/auth-store';
import { useAppStore } from '@/stores';
import { useLogout } from '@/hooks/use-auth';

const NAV_ITEMS = [
  { label: 'Overview',      href: '/dashboard',      icon: LayoutDashboard },
  { label: 'Chatbots',      href: '/chatbots',        icon: Bot },
  { label: 'Conversations', href: '/conversations',   icon: MessageSquare },
  { label: 'Analytics',     href: '/analytics',       icon: BarChart3 },
  { label: 'Team',          href: '/team',            icon: Users },
  { label: 'Settings',      href: '/settings',        icon: Settings },
] as const;

function getInitials(name: string): string {
  return name
    .split(' ')
    .map((n) => n[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
}

export function Sidebar() {
  const pathname = usePathname();
  const { sidebarOpen, toggleSidebar } = useAppStore();
  const user = useAuthStore((s) => s.user);
  const logoutMutation = useLogout();

  function isActive(href: string): boolean {
    if (href === '/dashboard') return pathname === '/dashboard';
    return pathname === href || pathname.startsWith(`${href}/`);
  }

  return (
    <aside
      className={cn(
        'hidden lg:flex flex-col border-r border-border bg-card transition-all duration-200 motion-reduce:duration-0 flex-shrink-0',
        sidebarOpen ? 'w-60' : 'w-14',
      )}
      aria-label="Main navigation"
    >
      {/* Header: logo + collapse toggle */}
      <div
        className={cn(
          'flex h-14 items-center border-b border-border px-3 flex-shrink-0',
          sidebarOpen ? 'justify-between' : 'justify-center',
        )}
      >
        {sidebarOpen && (
          <span className="font-display font-bold text-base tracking-tight text-foreground">
            ReplyIQ
          </span>
        )}
        <button
          onClick={toggleSidebar}
          className="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
          aria-label={sidebarOpen ? 'Collapse sidebar' : 'Expand sidebar'}
        >
          {sidebarOpen ? (
            <ChevronLeft className="h-4 w-4" aria-hidden />
          ) : (
            <ChevronRight className="h-4 w-4" aria-hidden />
          )}
        </button>
      </div>

      {/* Org switcher */}
      <div className={cn('px-2 pt-3 pb-1', !sidebarOpen && 'px-1')}>
        <button
          className={cn(
            'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground transition-colors',
            !sidebarOpen && 'justify-center px-0',
          )}
          aria-label="Switch organization"
        >
          <Building2 className="h-4 w-4 flex-shrink-0" aria-hidden />
          {sidebarOpen && (
            <span className="truncate text-left font-medium text-foreground">
              {user ? 'My Organization' : '—'}
            </span>
          )}
        </button>
      </div>

      <div className="mx-3 my-1 border-t border-border" />

      {/* Nav items */}
      <nav className="flex-1 overflow-y-auto px-2 py-2 space-y-0.5" aria-label="Primary">
        {NAV_ITEMS.map(({ label, href, icon: Icon }) => {
          const active = isActive(href);
          return (
            <Link
              key={href}
              href={href}
              className={cn(
                'flex items-center gap-3 rounded-md px-2 py-2 text-sm font-medium transition-colors',
                active
                  ? 'bg-primary/10 text-primary'
                  : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                !sidebarOpen && 'justify-center px-0',
              )}
              aria-current={active ? 'page' : undefined}
              title={!sidebarOpen ? label : undefined}
            >
              <Icon className="h-4 w-4 flex-shrink-0" aria-hidden />
              {sidebarOpen && <span>{label}</span>}
            </Link>
          );
        })}
      </nav>

      {/* User menu */}
      <div className="flex-shrink-0 border-t border-border p-2">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button
              className={cn(
                'flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-muted transition-colors',
                !sidebarOpen && 'justify-center px-0',
              )}
              aria-label="User menu"
            >
              <Avatar className="h-7 w-7 flex-shrink-0">
                <AvatarFallback className="text-xs bg-primary/10 text-primary">
                  {user?.name ? getInitials(user.name) : '?'}
                </AvatarFallback>
              </Avatar>
              {sidebarOpen && (
                <div className="flex-1 min-w-0 text-left">
                  <p className="truncate font-medium text-foreground text-sm leading-tight">
                    {user?.name ?? 'Loading…'}
                  </p>
                  <p className="truncate text-xs text-muted-foreground leading-tight">
                    {user?.email ?? ''}
                  </p>
                </div>
              )}
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent side="top" align="start" className="w-52">
            <DropdownMenuItem asChild>
              <Link href="/settings">
                <Settings className="mr-2 h-4 w-4" aria-hidden />
                Account settings
              </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              className="text-destructive focus:text-destructive"
              disabled={logoutMutation.isPending}
              onSelect={() => logoutMutation.mutate()}
            >
              <LogOut className="mr-2 h-4 w-4" aria-hidden />
              {logoutMutation.isPending ? 'Signing out…' : 'Sign out'}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </aside>
  );
}
