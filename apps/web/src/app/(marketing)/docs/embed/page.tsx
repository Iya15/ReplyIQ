import type { Metadata } from 'next';

export const metadata: Metadata = { title: 'Embed Guide — ReplyIQ Docs' };

export default function EmbedDocsPage() {
  return (
    <>
      <h1>Embed Guide</h1>
      <p className="lead">
        Add the ReplyIQ widget to any website with a single script tag.
      </p>

      <h2>Basic installation</h2>
      <pre><code>{`<script
  src="https://cdn.replyiq.com/widget.js"
  data-chatbot-id="YOUR_PUBLIC_ID"
  async
></script>`}</code></pre>
      <p>
        Paste this before the <code>&lt;/body&gt;</code> closing tag. The widget loads asynchronously
        and does not affect your Core Web Vitals.
      </p>

      <h2>Configuration options</h2>
      <table>
        <thead>
          <tr><th>Attribute</th><th>Required</th><th>Description</th></tr>
        </thead>
        <tbody>
          <tr><td><code>data-chatbot-id</code></td><td>Yes</td><td>Your chatbot&apos;s public ID (from the Embed tab)</td></tr>
          <tr><td><code>data-api-url</code></td><td>No</td><td>Override the API base URL (self-hosted only)</td></tr>
        </tbody>
      </table>

      <h2>Programmatic control</h2>
      <p>After the script loads, use the global <code>riq()</code> function:</p>
      <pre><code>{`// Open the chat programmatically
riq('open');

// Close the chat
riq('close');

// Identify the logged-in user
riq('setVisitor', { email: 'user@example.com', name: 'Jane' });

// Listen for events
window.addEventListener('message', (e) => {
  if (e.data?.type === 'riq:close') {
    console.log('Visitor closed the chat');
  }
});`}</code></pre>

      <h2>Content Security Policy (CSP)</h2>
      <p>If your site uses a CSP header, add these directives:</p>
      <pre><code>{`script-src 'self' https://cdn.replyiq.com;
frame-src  https://cdn.replyiq.com;
connect-src 'self' https://api.replyiq.com wss://reverb.replyiq.com;`}</code></pre>

      <h2>Allowed domains</h2>
      <p>
        To restrict which websites can load your chatbot, add allowed domains under{' '}
        <strong>Chatbots → Settings → Allowed Domains</strong>. Requests from unlisted origins are
        rejected with a 401.
      </p>
      <p>
        Leave empty to allow all origins (useful for testing and internal tools).
      </p>

      <h2>Testing locally</h2>
      <p>
        When developing locally, <code>localhost</code> is always allowed regardless of the
        allowed-domains setting. You can test the widget from <code>http://localhost:3000</code> or
        any local port without any configuration.
      </p>

      <h2>WordPress</h2>
      <p>
        Go to <strong>Appearance → Theme Editor → footer.php</strong> and paste the script tag
        before <code>&lt;/body&gt;</code>. Or use a plugin like &ldquo;Head, Footer and Post
        Injections&rdquo; to add it sitewide.
      </p>

      <h2>Webflow</h2>
      <p>
        Go to <strong>Project Settings → Custom Code → Footer Code</strong> and paste the script
        tag.
      </p>
    </>
  );
}
