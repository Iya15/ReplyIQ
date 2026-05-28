import Link from 'next/link';
import { Book, Rocket, Code2, HelpCircle, Terminal } from 'lucide-react';

const DOC_NAV = [
  { href: '/docs',                  label: 'Overview',          icon: Book },
  { href: '/docs/getting-started',  label: 'Getting Started',   icon: Rocket },
  { href: '/docs/embed',            label: 'Embed Guide',       icon: Code2 },
  { href: '/docs/api-reference',    label: 'API Reference',     icon: Terminal },
  { href: '/docs/troubleshooting',  label: 'Troubleshooting',   icon: HelpCircle },
] as const;

export default function DocsLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen">
      {/* Sidebar */}
      <aside className="hidden md:flex flex-col w-56 shrink-0 border-r py-8 px-4 space-y-1">
        <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground px-3 mb-4">
          Documentation
        </p>
        {DOC_NAV.map(({ href, label, icon: Icon }) => (
          <Link
            key={href}
            href={href}
            className="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-muted-foreground hover:text-foreground hover:bg-muted/60 transition-colors"
          >
            <Icon className="h-4 w-4 shrink-0" aria-hidden />
            {label}
          </Link>
        ))}
      </aside>

      {/* Content */}
      <div className="flex-1 py-12 px-6 md:px-12">
        <div className="mx-auto max-w-3xl prose prose-slate dark:prose-invert">
          {children}
        </div>
      </div>
    </div>
  );
}
