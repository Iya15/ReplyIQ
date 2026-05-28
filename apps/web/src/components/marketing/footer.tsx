import Link from 'next/link';

const LINKS = {
  Product:  [
    { label: 'Pricing',  href: '/pricing' },
    { label: 'Docs',     href: '/docs' },
    { label: 'Changelog', href: '/changelog' },
    { label: 'Status',   href: 'https://status.replyiq.com' },
  ],
  Company:  [
    { label: 'About',   href: '/about' },
    { label: 'Blog',    href: '/blog' },
    { label: 'Contact', href: 'mailto:hello@replyiq.com' },
  ],
  Legal:    [
    { label: 'Privacy Policy',      href: '/privacy' },
    { label: 'Terms of Service',    href: '/terms' },
    { label: 'Data Deletion',       href: '/data-deletion' },
    { label: 'Cookie Policy',       href: '/privacy#cookies' },
  ],
} as const;

export function MarketingFooter() {
  return (
    <footer className="border-t bg-muted/30 mt-24" aria-label="Site footer">
      <div className="mx-auto max-w-6xl px-6 py-12">
        <div className="grid grid-cols-2 gap-8 md:grid-cols-4">
          {/* Brand */}
          <div className="col-span-2 md:col-span-1 space-y-3">
            <Link href="/" className="font-bold text-lg">ReplyIQ</Link>
            <p className="text-sm text-muted-foreground max-w-[200px]">
              AI chatbots trained on your content. Deploy in minutes.
            </p>
          </div>

          {/* Links */}
          {Object.entries(LINKS).map(([section, links]) => (
            <div key={section}>
              <p className="text-sm font-semibold mb-3">{section}</p>
              <ul className="space-y-2">
                {links.map(({ label, href }) => (
                  <li key={label}>
                    <Link
                      href={href}
                      className="text-sm text-muted-foreground hover:text-foreground transition-colors"
                      {...(href.startsWith('http') ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                    >
                      {label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        <div className="mt-12 pt-8 border-t flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted-foreground">
          <p>© {new Date().getFullYear()} ReplyIQ. All rights reserved.</p>
          <p>GDPR compliant · SOC 2 in progress</p>
        </div>
      </div>
    </footer>
  );
}
