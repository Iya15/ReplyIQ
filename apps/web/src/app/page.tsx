import type { Metadata } from 'next';
import Link from 'next/link';
import { Bot, Zap, Shield, BarChart3, MessageSquare, FileText, Globe, CheckCircle } from 'lucide-react';
import { MarketingNav } from '@/components/marketing/nav';
import { MarketingFooter } from '@/components/marketing/footer';

export const metadata: Metadata = {
  title: 'ReplyIQ — AI Chatbots Trained on Your Content',
  description:
    'Deploy an AI chatbot trained on your documentation, FAQs, and website in minutes. No code required. GDPR compliant.',
  openGraph: {
    title: 'ReplyIQ — AI Chatbots Trained on Your Content',
    description: 'Deploy an AI chatbot trained on your documentation in minutes.',
    url: 'https://app.replyiq.com',
    siteName: 'ReplyIQ',
    type: 'website',
  },
  twitter: {
    card: 'summary_large_image',
    title: 'ReplyIQ — AI Chatbots Trained on Your Content',
    description: 'Deploy an AI chatbot trained on your documentation in minutes.',
  },
};

// ── Demo chat mockup (static) ─────────────────────────────────────────────────

const DEMO_MESSAGES = [
  { role: 'user',      content: 'What is your refund policy?' },
  { role: 'assistant', content: 'We offer a full refund within 30 days of purchase, no questions asked. After 30 days, refunds are available for annual plans on a pro-rated basis.' },
  { role: 'user',      content: 'Do you offer a free trial?' },
  { role: 'assistant', content: 'Yes! Our Free plan is free forever and includes 1 chatbot, 500 messages/month, and up to 10 documents. No credit card required.' },
];

function ChatDemo() {
  return (
    <div
      className="rounded-2xl border shadow-2xl overflow-hidden max-w-sm w-full"
      aria-label="Live chat demo preview"
      role="presentation"
    >
      {/* Header */}
      <div className="flex items-center gap-3 px-4 py-3 bg-[#4F46E5] text-white">
        <div className="h-8 w-8 rounded-full bg-white/20 flex items-center justify-center">
          <Bot className="h-4 w-4" />
        </div>
        <div>
          <p className="text-sm font-semibold">Acme Support</p>
          <p className="text-[10px] opacity-70">Powered by ReplyIQ</p>
        </div>
        <div className="ml-auto h-2 w-2 rounded-full bg-green-400" aria-hidden />
      </div>

      {/* Messages */}
      <div className="bg-white p-4 space-y-3 min-h-[260px]">
        {DEMO_MESSAGES.map((msg, i) => (
          <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : 'items-end gap-2'}`}>
            {msg.role === 'assistant' && (
              <div className="h-6 w-6 rounded-full bg-indigo-100 flex items-center justify-center shrink-0">
                <Bot className="h-3 w-3 text-indigo-600" />
              </div>
            )}
            <div
              className={`rounded-2xl px-3 py-2 text-[12px] leading-relaxed max-w-[80%] ${
                msg.role === 'user'
                  ? 'bg-[#4F46E5] text-white rounded-br-sm'
                  : 'bg-gray-100 text-gray-800 rounded-bl-sm'
              }`}
            >
              {msg.content}
            </div>
          </div>
        ))}
      </div>

      {/* Input */}
      <div className="border-t bg-white px-3 py-2.5 flex items-center gap-2">
        <div className="flex-1 rounded-lg border bg-gray-50 px-3 py-2 text-[12px] text-gray-400">
          Ask anything…
        </div>
        <div className="h-8 w-8 rounded-lg bg-[#4F46E5] flex items-center justify-center">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="white" aria-hidden="true">
            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
          </svg>
        </div>
      </div>
    </div>
  );
}

// ── Features ──────────────────────────────────────────────────────────────────

const FEATURES = [
  {
    icon:        Zap,
    title:       'Deploy in minutes',
    description: 'Upload your docs, set your brand colors, paste one line of code. Your chatbot is live.',
  },
  {
    icon:        FileText,
    title:       'Train on anything',
    description: 'PDFs, Word docs, plain text, FAQs, or your whole website. Supported out of the box.',
  },
  {
    icon:        Globe,
    title:       'Works everywhere',
    description: 'Embed on any website with a single script tag. Wordpress, Webflow, Shopify — all supported.',
  },
  {
    icon:        BarChart3,
    title:       'Full analytics',
    description: 'See every conversation, measure satisfaction, discover knowledge gaps. Act on real data.',
  },
  {
    icon:        MessageSquare,
    title:       'Human handoff',
    description: "When the AI can't help, visitors can request a human agent. You take over in the dashboard.",
  },
  {
    icon:        Shield,
    title:       'GDPR compliant',
    description: 'Data stored in EU. Data deletion on request. Cookie-free by default. Privacy built in.',
  },
];

// ── FAQ ───────────────────────────────────────────────────────────────────────

const FAQ = [
  {
    q: 'Do I need to know how to code?',
    a: 'No. You upload your documents through the dashboard, customize the chatbot appearance, and copy a single <script> tag into your website. That\'s it.',
  },
  {
    q: 'How accurate are the answers?',
    a: 'ReplyIQ uses RAG (Retrieval-Augmented Generation) — it only answers questions it can find in your documents. If it can\'t find a match, it says so instead of guessing.',
  },
  {
    q: 'What happens when the AI can\'t answer?',
    a: 'The chatbot shows your fallback message and offers a "Talk to a person" option. Your team can take over the conversation in real time from the dashboard.',
  },
  {
    q: 'Can I change the look and feel?',
    a: 'Yes. You can set your primary color, logo, welcome message, and chat position. The widget respects your brand.',
  },
  {
    q: 'Is my data secure?',
    a: 'Documents are stored encrypted at rest. The widget runs in a sandboxed iframe. Answers are generated via OpenAI\'s API with no data used for model training.',
  },
  {
    q: 'Can I cancel any time?',
    a: 'Yes. Cancel from the billing portal at any time. You keep access until the end of your billing period.',
  },
];

// ── Page ──────────────────────────────────────────────────────────────────────

export default function HomePage() {
  return (
    <div className="min-h-screen bg-background flex flex-col">
      <MarketingNav />
      <main>
        {/* ── Hero ────────────────────────────────────────────────────────── */}
        <section className="relative overflow-hidden py-20 md:py-32 px-6">
          {/* Background gradient */}
          <div
            className="absolute inset-0 -z-10"
            style={{
              background:
                'radial-gradient(ellipse 80% 50% at 50% -20%, rgba(79,70,229,0.15) 0%, transparent 70%)',
            }}
            aria-hidden
          />

          <div className="mx-auto max-w-6xl flex flex-col lg:flex-row items-center gap-16">
            {/* Text */}
            <div className="flex-1 space-y-6 text-center lg:text-left">
              <div className="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium text-muted-foreground bg-muted/60">
                <span className="h-1.5 w-1.5 rounded-full bg-green-500" />
                Now with human handoff
              </div>

              <h1 className="text-4xl md:text-5xl lg:text-6xl font-bold tracking-tight leading-tight">
                AI chatbots that{' '}
                <span
                  className="text-transparent bg-clip-text"
                  style={{ backgroundImage: 'linear-gradient(135deg, #4F46E5, #7C3AED)' }}
                >
                  know your product
                </span>
              </h1>

              <p className="text-lg md:text-xl text-muted-foreground max-w-xl mx-auto lg:mx-0">
                Train a chatbot on your docs in minutes. Embed it anywhere. Answer customer questions
                24/7 — automatically, accurately, in your voice.
              </p>

              <div className="flex flex-col sm:flex-row items-center gap-3 justify-center lg:justify-start">
                <Link
                  href="/register"
                  className="rounded-xl px-6 py-3 text-base font-semibold text-white transition-all hover:opacity-90 hover:scale-[1.02]"
                  style={{ background: 'linear-gradient(135deg, #4F46E5, #7C3AED)' }}
                >
                  Get started free
                </Link>
                <Link
                  href="/pricing"
                  className="rounded-xl px-6 py-3 text-base font-semibold border text-foreground hover:bg-muted transition-colors"
                >
                  See pricing
                </Link>
              </div>

              <p className="text-xs text-muted-foreground">
                Free plan · No credit card · Live in &lt; 3 minutes
              </p>
            </div>

            {/* Demo widget */}
            <div className="flex-shrink-0">
              <ChatDemo />
            </div>
          </div>
        </section>

        {/* ── Social proof ──────────────────────────────────────────────── */}
        <section className="py-8 px-6 border-y bg-muted/20">
          <div className="mx-auto max-w-6xl">
            <p className="text-center text-xs font-medium text-muted-foreground uppercase tracking-widest mb-6">
              Works with any website
            </p>
            <div className="flex flex-wrap items-center justify-center gap-8 text-sm font-semibold text-muted-foreground/60">
              {['Webflow', 'WordPress', 'Shopify', 'Wix', 'Framer', 'Custom HTML'].map((name) => (
                <span key={name}>{name}</span>
              ))}
            </div>
          </div>
        </section>

        {/* ── How it works ──────────────────────────────────────────────── */}
        <section className="py-24 px-6">
          <div className="mx-auto max-w-6xl">
            <div className="text-center space-y-3 mb-16">
              <h2 className="text-3xl font-bold tracking-tight">Live in three steps</h2>
              <p className="text-muted-foreground max-w-lg mx-auto">
                From signup to answering customer questions in under 3 minutes.
              </p>
            </div>

            <div className="grid grid-cols-1 gap-8 md:grid-cols-3">
              {[
                { step: '01', title: 'Upload your content', desc: 'Drop in PDFs, paste text, or let ReplyIQ crawl your website. The AI reads and indexes everything.' },
                { step: '02', title: 'Customize the widget', desc: 'Set your brand color, logo, and welcome message. Preview in real time.' },
                { step: '03', title: 'Paste one script tag', desc: 'Copy the embed snippet and paste it into your website\'s HTML. Done.' },
              ].map(({ step, title, desc }) => (
                <div key={step} className="relative rounded-2xl border bg-card p-6 space-y-3">
                  <div
                    className="inline-flex h-10 w-10 items-center justify-center rounded-xl text-sm font-bold text-white"
                    style={{ background: 'linear-gradient(135deg, #4F46E5, #7C3AED)' }}
                  >
                    {step}
                  </div>
                  <h3 className="text-base font-semibold">{title}</h3>
                  <p className="text-sm text-muted-foreground leading-relaxed">{desc}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ── Features ──────────────────────────────────────────────────── */}
        <section className="py-24 px-6 bg-muted/20">
          <div className="mx-auto max-w-6xl">
            <div className="text-center space-y-3 mb-16">
              <h2 className="text-3xl font-bold tracking-tight">Everything you need</h2>
            </div>

            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {FEATURES.map(({ icon: Icon, title, description }) => (
                <div key={title} className="rounded-2xl border bg-card p-6 space-y-3 hover:shadow-md transition-shadow">
                  <div className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10">
                    <Icon className="h-5 w-5 text-primary" aria-hidden />
                  </div>
                  <h3 className="font-semibold">{title}</h3>
                  <p className="text-sm text-muted-foreground leading-relaxed">{description}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ── Testimonials ──────────────────────────────────────────────── */}
        <section className="py-24 px-6">
          <div className="mx-auto max-w-6xl">
            <div className="text-center space-y-3 mb-16">
              <h2 className="text-3xl font-bold tracking-tight">Loved by teams like yours</h2>
              <p className="text-muted-foreground">Real stories coming soon — we&apos;re in early access.</p>
            </div>

            <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
              {[
                {
                  quote:  '"Our support team handles 40% fewer repetitive questions since deploying ReplyIQ."',
                  name:   'Sarah K.',
                  role:   'Head of Customer Success',
                  company: 'Acme Corp',
                },
                {
                  quote:  '"Set up in literally 8 minutes. The answers are surprisingly accurate — customers can\'t tell it\'s AI."',
                  name:   'James T.',
                  role:   'Founder',
                  company: 'Launchpad SaaS',
                },
                {
                  quote:  '"The human handoff feature is a game-changer. Tricky questions go to a real person seamlessly."',
                  name:   'Maria L.',
                  role:   'Product Manager',
                  company: 'FlowDesk',
                },
              ].map(({ quote, name, role, company }) => (
                <figure key={name} className="rounded-2xl border bg-card p-6 space-y-4">
                  <blockquote className="text-sm leading-relaxed text-muted-foreground">{quote}</blockquote>
                  <figcaption className="flex items-center gap-3">
                    <div className="h-9 w-9 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary">
                      {name[0]}
                    </div>
                    <div>
                      <p className="text-sm font-semibold">{name}</p>
                      <p className="text-xs text-muted-foreground">{role}, {company}</p>
                    </div>
                  </figcaption>
                </figure>
              ))}
            </div>
          </div>
        </section>

        {/* ── Pricing CTA ───────────────────────────────────────────────── */}
        <section className="py-24 px-6 bg-muted/20">
          <div className="mx-auto max-w-3xl text-center space-y-8">
            <h2 className="text-3xl font-bold tracking-tight">Simple, transparent pricing</h2>
            <p className="text-muted-foreground">
              Start free. No credit card. Upgrade when you need more.
            </p>
            <div className="flex flex-wrap items-center justify-center gap-4">
              {[
                { plan: 'Free', price: '$0', note: 'forever' },
                { plan: 'Starter', price: '$29', note: '/month' },
                { plan: 'Pro', price: '$89', note: '/month' },
                { plan: 'Business', price: '$249', note: '/month' },
              ].map(({ plan, price, note }) => (
                <div key={plan} className="rounded-xl border bg-card px-5 py-3 text-center min-w-[110px]">
                  <p className="text-xs text-muted-foreground">{plan}</p>
                  <p className="font-bold text-lg">{price}<span className="text-xs font-normal text-muted-foreground">{note}</span></p>
                </div>
              ))}
            </div>
            <Link
              href="/pricing"
              className="inline-block rounded-xl px-6 py-3 text-sm font-semibold border hover:bg-muted transition-colors"
            >
              See all features →
            </Link>
          </div>
        </section>

        {/* ── FAQ ───────────────────────────────────────────────────────── */}
        <section className="py-24 px-6" aria-labelledby="faq-heading">
          <div className="mx-auto max-w-3xl">
            <h2 id="faq-heading" className="text-3xl font-bold tracking-tight text-center mb-12">
              Frequently asked questions
            </h2>
            <div className="space-y-2">
              {FAQ.map(({ q, a }) => (
                <details
                  key={q}
                  className="group rounded-xl border bg-card overflow-hidden"
                >
                  <summary className="flex items-center justify-between px-5 py-4 cursor-pointer text-sm font-semibold list-none select-none hover:bg-muted/40 transition-colors">
                    {q}
                    <span className="text-muted-foreground group-open:rotate-45 transition-transform">+</span>
                  </summary>
                  <div className="px-5 pb-4 text-sm text-muted-foreground leading-relaxed">
                    {a}
                  </div>
                </details>
              ))}
            </div>
          </div>
        </section>

        {/* ── CTA Banner ────────────────────────────────────────────────── */}
        <section className="py-24 px-6">
          <div
            className="mx-auto max-w-4xl rounded-3xl p-12 text-center text-white space-y-6"
            style={{ background: 'linear-gradient(135deg, #3730A3 0%, #4F46E5 50%, #7C3AED 100%)' }}
          >
            <h2 className="text-3xl font-bold">Ready to automate your support?</h2>
            <p className="text-white/80 max-w-xl mx-auto">
              Join hundreds of businesses using ReplyIQ to answer customer questions automatically.
              Free forever. No credit card.
            </p>
            <div className="flex flex-col sm:flex-row items-center justify-center gap-3">
              <Link
                href="/register"
                className="rounded-xl bg-white px-6 py-3 text-sm font-semibold text-indigo-700 hover:bg-white/90 transition-colors"
              >
                Start for free
              </Link>
              <Link
                href="/docs"
                className="rounded-xl border border-white/30 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10 transition-colors"
              >
                Read the docs
              </Link>
            </div>
          </div>
        </section>
      </main>

      <MarketingFooter />
    </div>
  );
}
