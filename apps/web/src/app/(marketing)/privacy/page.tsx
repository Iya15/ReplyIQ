import type { Metadata } from 'next';

export const metadata: Metadata = { title: 'Privacy Policy' };

export default function PrivacyPage() {
  const updated = '29 May 2026';

  return (
    <div className="py-16 px-6">
      <div className="mx-auto max-w-3xl prose prose-slate dark:prose-invert">
        <h1>Privacy Policy</h1>
        <p className="text-muted-foreground text-sm">Last updated: {updated}</p>

        <p>
          ReplyIQ (&ldquo;we&rdquo;, &ldquo;us&rdquo;, or &ldquo;our&rdquo;) operates the ReplyIQ
          platform. This Privacy Policy explains how we collect, use, and protect your information
          when you use our service.
        </p>

        <h2>1. Information We Collect</h2>
        <h3>Account data</h3>
        <p>
          When you register, we collect your name, email address, and (if you subscribe) billing
          information processed by Stripe. We do not store card numbers — Stripe handles payment data.
        </p>

        <h3>Content data</h3>
        <p>
          Documents, FAQs, and website content you upload to train your chatbot are stored on your
          behalf. We process this content to generate vector embeddings for retrieval.
        </p>

        <h3>Conversation data</h3>
        <p>
          Messages exchanged between your chatbot and visitors are stored to power the Conversations
          dashboard and analytics. Visitor IP addresses and User-Agent strings are collected for
          fraud prevention and analytics.
        </p>

        <h3>Analytics</h3>
        <p>
          We collect aggregated usage data (message counts, topic frequencies, latency metrics) to
          improve the service. We do <strong>not</strong> use third-party analytics cookies.
        </p>

        <h2>2. How We Use Your Data</h2>
        <ul>
          <li>To operate and improve the ReplyIQ platform.</li>
          <li>To send transactional emails (verification, password reset, plan alerts).</li>
          <li>To process billing and prevent fraud.</li>
          <li>To monitor uptime and performance.</li>
        </ul>
        <p>We do not sell your data to third parties.</p>

        <h2>3. Data Storage &amp; Security</h2>
        <p>
          Data is stored on Supabase (PostgreSQL) and Cloudflare R2. Servers are located in the EU
          (us-east-1 or eu-west-1 depending on your Supabase region selection). Data in transit is
          encrypted with TLS 1.2+. Data at rest is encrypted with AES-256.
        </p>

        <h2>4. Data Retention</h2>
        <p>
          We retain your data for as long as your account is active. If you delete your account, we
          delete your personal data within 30 days. Anonymised analytics data may be retained longer.
        </p>

        <h2>5. Your Rights (GDPR / CCPA)</h2>
        <p>You have the right to:</p>
        <ul>
          <li><strong>Access</strong> the data we hold about you.</li>
          <li><strong>Correct</strong> inaccurate data.</li>
          <li><strong>Delete</strong> your data (&ldquo;right to be forgotten&rdquo;).</li>
          <li><strong>Port</strong> your data in a machine-readable format.</li>
          <li><strong>Object</strong> to certain processing.</li>
        </ul>
        <p>
          To exercise these rights, submit a request at{' '}
          <a href="/data-deletion">/data-deletion</a> or email{' '}
          <a href="mailto:privacy@replyiq.com">privacy@replyiq.com</a>.
        </p>

        <h2 id="cookies">6. Cookies</h2>
        <p>
          ReplyIQ does <strong>not</strong> use tracking or advertising cookies. We use a session
          cookie for authentication that expires when you close your browser. No third-party cookies
          are set.
        </p>

        <h2>7. Third-Party Services</h2>
        <ul>
          <li><strong>OpenAI</strong> — for AI inference. Your documents are sent to OpenAI's API but are not used to train their models (per their API data usage policy).</li>
          <li><strong>Stripe</strong> — for payment processing. Stripe has its own Privacy Policy.</li>
          <li><strong>Sentry</strong> — for error monitoring. Error reports may include stack traces and request metadata.</li>
          <li><strong>Better Stack</strong> — for log aggregation. Logs may include request IDs and error messages.</li>
        </ul>

        <h2>8. Contact</h2>
        <p>
          Questions? Contact us at <a href="mailto:privacy@replyiq.com">privacy@replyiq.com</a>.
        </p>
      </div>
    </div>
  );
}
