import Link from 'next/link';

export default function MarketingLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-background">
      <header className="border-b">
        <div className="mx-auto max-w-6xl px-6 h-14 flex items-center justify-between">
          <Link href="/" className="font-bold text-lg tracking-tight">
            ReplyIQ
          </Link>
          <nav className="flex items-center gap-4 text-sm">
            <Link href="/pricing" className="text-muted-foreground hover:text-foreground transition-colors">
              Pricing
            </Link>
            <Link href="/login" className="text-muted-foreground hover:text-foreground transition-colors">
              Sign in
            </Link>
            <Link
              href="/register"
              className="rounded-md bg-primary px-3.5 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
            >
              Get started
            </Link>
          </nav>
        </div>
      </header>
      <main>{children}</main>
      <footer className="border-t mt-24">
        <div className="mx-auto max-w-6xl px-6 h-14 flex items-center text-xs text-muted-foreground">
          © {new Date().getFullYear()} ReplyIQ
        </div>
      </footer>
    </div>
  );
}
