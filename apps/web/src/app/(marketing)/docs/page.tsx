import type { Metadata } from 'next';
import Link from 'next/link';
import { Rocket, Code2, Terminal, HelpCircle } from 'lucide-react';

export const metadata: Metadata = { title: 'Documentation' };

const SECTIONS = [
  { href: '/docs/getting-started', icon: Rocket,     title: 'Getting Started',   desc: 'Sign up, create your first chatbot, and embed it in under 3 minutes.' },
  { href: '/docs/embed',           icon: Code2,       title: 'Embed Guide',       desc: 'Add the widget to any website, configure CORS, and test locally.' },
  { href: '/docs/api-reference',   icon: Terminal,    title: 'API Reference',     desc: 'Programmatic access via API keys. REST endpoints documented.' },
  { href: '/docs/troubleshooting', icon: HelpCircle,  title: 'Troubleshooting',   desc: 'Common issues and how to fix them.' },
];

export default function DocsIndexPage() {
  return (
    <>
      <h1>ReplyIQ Documentation</h1>
      <p className="lead">
        Everything you need to deploy AI chatbots trained on your content.
      </p>

      <div className="not-prose grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
        {SECTIONS.map(({ href, icon: Icon, title, desc }) => (
          <Link
            key={href}
            href={href}
            className="group rounded-xl border bg-card p-5 hover:shadow-md transition-all hover:border-primary/50 space-y-2"
          >
            <div className="flex items-center gap-2">
              <Icon className="h-5 w-5 text-primary" aria-hidden />
              <span className="font-semibold text-sm group-hover:text-primary transition-colors">{title}</span>
            </div>
            <p className="text-xs text-muted-foreground leading-relaxed">{desc}</p>
          </Link>
        ))}
      </div>
    </>
  );
}
