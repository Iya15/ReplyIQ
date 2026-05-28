import type { Metadata } from 'next';
import Link from 'next/link';
import { Key, User } from 'lucide-react';

export const metadata: Metadata = { title: 'Settings' };

const SETTING_SECTIONS = [
  {
    href:        '/settings/api-keys',
    icon:        Key,
    title:       'API Keys',
    description: 'Create and manage API keys for programmatic access.',
  },
  {
    href:        '/settings/account',
    icon:        User,
    title:       'Account',
    description: 'Update your name, email, and password.',
    disabled:    true,
  },
] as const;

export default function SettingsPage() {
  return (
    <div className="p-6 max-w-2xl mx-auto space-y-6">
      <div>
        <h1 className="text-xl font-semibold">Settings</h1>
        <p className="text-sm text-muted-foreground mt-0.5">Manage your account and organization settings.</p>
      </div>

      <div className="grid gap-3">
        {SETTING_SECTIONS.map(({ href, icon: Icon, title, description, ...rest }) => {
          const disabled = 'disabled' in rest && rest.disabled;
          const inner = (
            <div className={`flex items-start gap-4 rounded-xl border p-5 transition-colors ${disabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-muted/40 cursor-pointer'}`}>
              <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                <Icon className="h-4.5 w-4.5 text-primary" />
              </div>
              <div>
                <p className="font-medium text-sm">{title}</p>
                <p className="text-xs text-muted-foreground mt-0.5">{description}</p>
              </div>
            </div>
          );
          return disabled ? <div key={href}>{inner}</div> : <Link key={href} href={href}>{inner}</Link>;
        })}
      </div>
    </div>
  );
}
