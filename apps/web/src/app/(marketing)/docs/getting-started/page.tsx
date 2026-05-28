import type { Metadata } from 'next';
import Link from 'next/link';

export const metadata: Metadata = { title: 'Getting Started — ReplyIQ Docs' };

export default function GettingStartedPage() {
  return (
    <>
      <h1>Getting Started</h1>
      <p className="lead">
        From signup to a live chatbot in under 3 minutes.
      </p>

      <h2>Step 1 — Create an account</h2>
      <p>
        Go to <Link href="/register">app.replyiq.com/register</Link> and sign up with your email.
        You will receive a verification email — click the link to activate your account.
      </p>
      <p>
        On first login, ReplyIQ creates a sample chatbot pre-populated with demo content so you can
        see how the system works immediately.
      </p>

      <h2>Step 2 — Add your content</h2>
      <p>Navigate to <strong>Chatbots → Your Chatbot → Knowledge Base</strong> and upload your content:</p>
      <ul>
        <li><strong>Upload file</strong> — PDF, DOCX, or TXT. Max 25 MB per file.</li>
        <li><strong>Add text / FAQ</strong> — Paste plain text or a list of Q&amp;A pairs.</li>
        <li><strong>Crawl website</strong> — Enter a URL and we&apos;ll index up to 50 pages.</li>
      </ul>
      <p>Processing typically takes 10–60 seconds per document. Status updates in real time.</p>

      <h2>Step 3 — Customize</h2>
      <p>
        Under <strong>Customize</strong>, set your brand color, logo, welcome message, and chat
        position. Changes preview instantly in the panel.
      </p>

      <h2>Step 4 — Embed</h2>
      <p>
        Go to <strong>Embed</strong> and copy the snippet. Paste it before the <code>&lt;/body&gt;</code> tag on
        your website:
      </p>
      <pre><code>{`<script
  src="https://cdn.replyiq.com/widget.js"
  data-chatbot-id="YOUR_PUBLIC_ID"
  async
></script>`}</code></pre>
      <p>
        The widget loads lazily — it does not block your page. Replace{' '}
        <code>YOUR_PUBLIC_ID</code> with your chatbot&apos;s public ID from the Embed tab.
      </p>

      <h2>Step 5 — Test it</h2>
      <p>
        Open your website in a browser and click the chat launcher in the corner. Ask a question
        that is answered in your documents. The bot should reply accurately within a few seconds.
      </p>

      <h2>Next steps</h2>
      <ul>
        <li><Link href="/docs/embed">Advanced embedding options</Link></li>
        <li><Link href="/docs/api-reference">Programmatic API access</Link></li>
        <li><Link href="/docs/troubleshooting">Troubleshooting</Link></li>
      </ul>
    </>
  );
}
