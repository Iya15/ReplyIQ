import type { Metadata } from 'next';

export const metadata: Metadata = { title: 'Terms of Service' };

export default function TermsPage() {
  const updated = '29 May 2026';

  return (
    <div className="py-16 px-6">
      <div className="mx-auto max-w-3xl prose prose-slate dark:prose-invert">
        <h1>Terms of Service</h1>
        <p className="text-muted-foreground text-sm">Last updated: {updated}</p>

        <p>
          These Terms of Service (&ldquo;Terms&rdquo;) govern your use of ReplyIQ. By accessing or
          using our service, you agree to be bound by these Terms.
        </p>

        <h2>1. Use of the Service</h2>
        <p>You may use ReplyIQ to:</p>
        <ul>
          <li>Create AI chatbots trained on your own content.</li>
          <li>Embed chatbots on websites you own or operate.</li>
          <li>Manage conversations and analytics for your chatbots.</li>
        </ul>
        <p>You may not:</p>
        <ul>
          <li>Use the service to generate harmful, illegal, or deceptive content.</li>
          <li>Reverse-engineer, scrape, or resell the platform.</li>
          <li>Upload content that infringes intellectual property rights.</li>
          <li>Attempt to circumvent usage limits or access restrictions.</li>
        </ul>

        <h2>2. Accounts</h2>
        <p>
          You are responsible for maintaining the security of your account credentials. Notify us
          immediately at <a href="mailto:security@replyiq.com">security@replyiq.com</a> if you
          suspect unauthorised access.
        </p>

        <h2>3. Content</h2>
        <p>
          You retain ownership of the content you upload. By uploading content, you grant ReplyIQ a
          limited licence to process it for the purpose of providing the service (generating
          embeddings, building chatbot responses). We do not use your content to train our own models.
        </p>

        <h2>4. Billing &amp; Cancellation</h2>
        <ul>
          <li>Paid plans are billed monthly or annually in advance.</li>
          <li>You can cancel at any time via the billing portal. Access continues until the end of the current billing period.</li>
          <li>We do not offer refunds for partial months, except for annual plans cancelled within 14 days of purchase.</li>
        </ul>

        <h2>5. Plan Limits</h2>
        <p>
          Usage is subject to the limits of your plan. If you exceed your plan&apos;s limits, the service
          may restrict new conversations or messages until the next billing period or you upgrade.
        </p>

        <h2>6. Service Availability</h2>
        <p>
          We aim for 99.9% uptime. Planned maintenance will be announced at{' '}
          <a href="https://status.replyiq.com" target="_blank" rel="noopener noreferrer">
            status.replyiq.com
          </a>
          . We are not liable for downtime caused by third-party services (OpenAI, Stripe, etc.).
        </p>

        <h2>7. Limitation of Liability</h2>
        <p>
          To the maximum extent permitted by law, ReplyIQ is not liable for indirect, incidental, or
          consequential damages arising from your use of the service. Our total liability for any
          claim is limited to the amount you paid in the past 12 months.
        </p>

        <h2>8. Indemnification</h2>
        <p>
          You agree to indemnify ReplyIQ from claims arising from your use of the service, your
          content, or your violation of these Terms.
        </p>

        <h2>9. Termination</h2>
        <p>
          We may suspend or terminate your account for violation of these Terms. You may delete your
          account at any time; data will be removed within 30 days per our Privacy Policy.
        </p>

        <h2>10. Governing Law</h2>
        <p>
          These Terms are governed by the laws of the jurisdiction in which ReplyIQ is incorporated.
          Disputes shall be resolved by binding arbitration.
        </p>

        <h2>11. Changes</h2>
        <p>
          We may update these Terms. We will notify you by email and update the &ldquo;Last
          updated&rdquo; date. Continued use after 30 days constitutes acceptance.
        </p>

        <h2>12. Contact</h2>
        <p>
          Questions? <a href="mailto:legal@replyiq.com">legal@replyiq.com</a>
        </p>
      </div>
    </div>
  );
}
