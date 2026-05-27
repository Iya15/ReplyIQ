import type { Metadata } from 'next';

export const metadata: Metadata = { title: 'Account Settings' };

export default function SettingsPage() {
  return (
    <div>
      <h1 className="text-2xl font-semibold mb-1">Account Settings</h1>
      <p className="text-muted-foreground text-sm">Settings coming in M2.</p>
    </div>
  );
}
