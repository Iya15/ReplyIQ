import Link from 'next/link';

export function MarketingNav() {
  return (
    <header className="border-b bg-background/95 backdrop-blur sticky top-0 z-50">
      <div className="mx-auto max-w-6xl px-6 h-16 flex items-center justify-between">
        <Link href="/" className="font-bold text-xl tracking-tight text-foreground">
          ReplyIQ
        </Link>
        <nav className="hidden md:flex items-center gap-6 text-sm" aria-label="Main">
          <Link href="/pricing" className="text-muted-foreground hover:text-foreground transition-colors">
            Pricing
          </Link>
          <Link href="/docs" className="text-muted-foreground hover:text-foreground transition-colors">
            Docs
          </Link>
          <Link href="/login" className="text-muted-foreground hover:text-foreground transition-colors">
            Sign in
          </Link>
          <Link
            href="/register"
            className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition-colors"
          >
            Get started free
          </Link>
        </nav>
        {/* Mobile: just show Get started */}
        <Link
          href="/register"
          className="md:hidden rounded-lg bg-primary px-3.5 py-1.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition-colors"
        >
          Get started
        </Link>
      </div>
    </header>
  );
}
