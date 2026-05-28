import type { Metadata } from 'next';

export const metadata: Metadata = { title: 'Troubleshooting — ReplyIQ Docs' };

export default function TroubleshootingPage() {
  return (
    <>
      <h1>Troubleshooting</h1>

      <h2>Widget doesn&apos;t appear on my website</h2>
      <ol>
        <li>Open DevTools → Console. Is there a script load error?</li>
        <li>Confirm the script tag is before <code>&lt;/body&gt;</code>, not inside <code>&lt;head&gt;</code>.</li>
        <li>Check your Content Security Policy — add <code>script-src https://cdn.replyiq.com</code>.</li>
        <li>
          Check <strong>Settings → Allowed Domains</strong>. Add your domain or clear the list to allow all.
        </li>
        <li>Hard-refresh (Ctrl+Shift+R / Cmd+Shift+R) to bypass browser cache.</li>
      </ol>

      <h2>The bot says &ldquo;I don&apos;t have enough information&rdquo; for everything</h2>
      <ol>
        <li>Check the Knowledge Base tab — are documents showing as &ldquo;Ready&rdquo;? If still processing, wait 30–60 seconds.</li>
        <li>Make sure the question matches the language in your documents. The bot retrieves based on semantic similarity.</li>
        <li>Try lowering the similarity threshold under <strong>Customize → AI Config → Similarity Threshold</strong> (default 0.75 → try 0.65).</li>
        <li>If a document shows &ldquo;Failed&rdquo;, click Reprocess or re-upload it.</li>
      </ol>

      <h2>Document processing is stuck on &ldquo;Processing&rdquo;</h2>
      <p>
        Processing uses background workers. If it has been &gt;5 minutes:
      </p>
      <ol>
        <li>Click the &ldquo;Reprocess&rdquo; button in the Knowledge Base.</li>
        <li>Check if the OpenAI API is experiencing issues at{' '}
          <a href="https://status.openai.com" target="_blank" rel="noopener noreferrer">
            status.openai.com
          </a>.
        </li>
        <li>If the file is very large (&gt;10 MB), try splitting it into smaller files.</li>
      </ol>

      <h2>Answers are slow (&gt;10 seconds)</h2>
      <p>AI response time depends on OpenAI API latency. Expected times:</p>
      <ul>
        <li><strong>GPT-4o Mini</strong>: 1–4 seconds for most responses.</li>
        <li><strong>GPT-4o</strong>: 3–8 seconds.</li>
      </ul>
      <p>
        Streaming is enabled by default — visitors see the first words within ~500ms even for longer
        responses. If you see constant &gt;10s replies, check{' '}
        <a href="https://status.openai.com" target="_blank" rel="noopener noreferrer">
          OpenAI&apos;s status page
        </a>.
      </p>

      <h2>Widget works locally but not on my live site</h2>
      <ol>
        <li>Check <strong>Settings → Allowed Domains</strong> — add your live domain (e.g., <code>example.com</code>).</li>
        <li>Verify the <code>data-chatbot-id</code> matches your chatbot&apos;s public ID.</li>
        <li>Check for mixed-content warnings (HTTP site loading HTTPS scripts).</li>
      </ol>

      <h2>Emails are not arriving</h2>
      <ol>
        <li>Check your spam / promotions folder.</li>
        <li>Add <code>noreply@replyiq.com</code> to your contacts.</li>
        <li>Contact <a href="mailto:support@replyiq.com">support@replyiq.com</a> with your email address.</li>
      </ol>

      <h2>Still stuck?</h2>
      <p>
        Email us at <a href="mailto:support@replyiq.com">support@replyiq.com</a> with:
      </p>
      <ul>
        <li>Your account email.</li>
        <li>The chatbot public ID.</li>
        <li>A screenshot or console log of the error.</li>
      </ul>
    </>
  );
}
