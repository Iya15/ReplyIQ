import { Check } from 'lucide-react';

const FEATURES = [
  'Train on docs, URLs, and plain text',
  'Embed anywhere in under 2 minutes',
  'Analytics for every conversation',
];

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen flex">
      {/* Left: form panel */}
      <div className="flex flex-1 flex-col items-center justify-center px-6 py-12 bg-background">
        <div className="w-full max-w-[400px] space-y-8">
          {/* Mobile logo */}
          <div className="lg:hidden text-center">
            <span className="font-display font-bold text-2xl text-foreground">ReplyIQ</span>
          </div>
          {children}
        </div>
      </div>

      {/* Right: brand panel — hidden on mobile */}
      <div
        className="hidden lg:flex flex-1 flex-col items-center justify-center p-12 relative overflow-hidden"
        style={{
          background: 'linear-gradient(135deg, #3730A3 0%, #4F46E5 50%, #7C3AED 100%)',
        }}
        aria-hidden
      >
        {/* Decorative blobs */}
        <div className="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-white/5" />
        <div className="absolute -bottom-32 -left-16 w-96 h-96 rounded-full bg-white/5" />

        {/* Content */}
        <div className="relative z-10 text-white max-w-sm space-y-8">
          <div className="space-y-2">
            <p className="text-sm font-medium tracking-widest uppercase text-white/50">ReplyIQ</p>
            <h2 className="text-3xl font-bold font-display leading-tight tracking-tight">
              AI chatbots that actually know your product
            </h2>
            <p className="text-white/70 text-base leading-relaxed">
              Train on your docs, deploy in minutes, and let your customers get answers 24/7.
            </p>
          </div>

          <ul className="space-y-3">
            {FEATURES.map((f) => (
              <li key={f} className="flex items-center gap-3 text-white/90 text-sm">
                <span className="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-white/20">
                  <Check className="h-3 w-3" />
                </span>
                {f}
              </li>
            ))}
          </ul>

          {/* Mock widget card */}
          <div className="rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 p-4 space-y-3">
            <div className="flex items-center gap-2">
              <div className="h-6 w-6 rounded-full bg-white/30" />
              <div className="h-2.5 w-20 rounded bg-white/30" />
            </div>
            <div className="space-y-2">
              <div className="h-2 w-full rounded bg-white/20" />
              <div className="h-2 w-4/5 rounded bg-white/20" />
            </div>
            <div className="h-8 rounded-lg bg-white/20 flex items-center px-3">
              <div className="h-2 w-28 rounded bg-white/30" />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
