import type { Metadata } from 'next';

export const metadata: Metadata = { title: 'API Reference — ReplyIQ Docs' };

export default function ApiReferencePage() {
  return (
    <>
      <h1>API Reference</h1>
      <p className="lead">
        Programmatic access to ReplyIQ. Create chatbots, manage documents, and query analytics
        from your own code.
      </p>

      <h2>Authentication</h2>
      <p>
        All API requests require an API key in the <code>Authorization</code> header:
      </p>
      <pre><code>{`Authorization: Bearer <your_api_key>`}</code></pre>
      <p>
        Create API keys under <strong>Settings → API Keys</strong>. Keys are shown once at creation
        — store them securely.
      </p>

      <h2>Base URL</h2>
      <pre><code>https://api.replyiq.com/api/v1</code></pre>

      <h2>Rate Limits</h2>
      <table>
        <thead><tr><th>Endpoint group</th><th>Limit</th></tr></thead>
        <tbody>
          <tr><td>Authentication</td><td>5 req/min per IP</td></tr>
          <tr><td>Widget (public)</td><td>60 req/min per IP</td></tr>
          <tr><td>Widget send-message</td><td>30 msg/min per conversation</td></tr>
          <tr><td>All other endpoints</td><td>No hard limit (fair use)</td></tr>
        </tbody>
      </table>

      <h2>Endpoints</h2>

      <h3>Chatbots</h3>
      <table>
        <thead><tr><th>Method</th><th>Path</th><th>Description</th></tr></thead>
        <tbody>
          <tr><td>GET</td><td>/chatbots</td><td>List all chatbots</td></tr>
          <tr><td>POST</td><td>/chatbots</td><td>Create a chatbot</td></tr>
          <tr><td>GET</td><td>/chatbots/:id</td><td>Get a chatbot</td></tr>
          <tr><td>PATCH</td><td>/chatbots/:id</td><td>Update a chatbot</td></tr>
          <tr><td>DELETE</td><td>/chatbots/:id</td><td>Delete a chatbot</td></tr>
          <tr><td>GET</td><td>/chatbots/:id/settings</td><td>Get widget settings</td></tr>
          <tr><td>PATCH</td><td>/chatbots/:id/settings</td><td>Update widget settings</td></tr>
        </tbody>
      </table>

      <h3>Documents</h3>
      <table>
        <thead><tr><th>Method</th><th>Path</th><th>Description</th></tr></thead>
        <tbody>
          <tr><td>GET</td><td>/chatbots/:id/documents</td><td>List documents</td></tr>
          <tr><td>POST</td><td>/chatbots/:id/documents</td><td>Upload a file (multipart/form-data)</td></tr>
          <tr><td>POST</td><td>/chatbots/:id/documents/text</td><td>Add plain text / FAQ</td></tr>
          <tr><td>POST</td><td>/chatbots/:id/documents/url</td><td>Crawl a URL</td></tr>
          <tr><td>DELETE</td><td>/documents/:id</td><td>Delete a document</td></tr>
          <tr><td>POST</td><td>/documents/:id/reprocess</td><td>Re-embed a document</td></tr>
        </tbody>
      </table>

      <h3>Conversations</h3>
      <table>
        <thead><tr><th>Method</th><th>Path</th><th>Description</th></tr></thead>
        <tbody>
          <tr><td>GET</td><td>/chatbots/:id/conversations</td><td>List conversations</td></tr>
          <tr><td>GET</td><td>/conversations/:id</td><td>Get a conversation</td></tr>
          <tr><td>GET</td><td>/conversations/:id/messages</td><td>Get messages</td></tr>
          <tr><td>POST</td><td>/conversations/:id/resolve</td><td>Mark resolved</td></tr>
          <tr><td>POST</td><td>/conversations/:id/takeover</td><td>Agent takeover</td></tr>
        </tbody>
      </table>

      <h3>Analytics</h3>
      <table>
        <thead><tr><th>Method</th><th>Path</th><th>Description</th></tr></thead>
        <tbody>
          <tr><td>GET</td><td>/chatbots/:id/analytics/overview</td><td>KPIs (range=7d|30d|90d)</td></tr>
          <tr><td>GET</td><td>/chatbots/:id/analytics/conversations</td><td>Daily conversation counts</td></tr>
          <tr><td>GET</td><td>/chatbots/:id/analytics/topics</td><td>Top question topics</td></tr>
          <tr><td>GET</td><td>/chatbots/:id/analytics/unanswered</td><td>Unanswered questions</td></tr>
        </tbody>
      </table>

      <h2>Errors</h2>
      <p>All errors return a JSON body with <code>error.code</code> and <code>error.message</code>:</p>
      <pre><code>{`{
  "error": {
    "code": "chatbot_not_found",
    "message": "Chatbot not found."
  }
}`}</code></pre>

      <table>
        <thead><tr><th>HTTP Status</th><th>Meaning</th></tr></thead>
        <tbody>
          <tr><td>400</td><td>Bad request</td></tr>
          <tr><td>401</td><td>Missing or invalid API key</td></tr>
          <tr><td>402</td><td>Plan limit exceeded</td></tr>
          <tr><td>403</td><td>Forbidden (wrong tenant)</td></tr>
          <tr><td>404</td><td>Resource not found</td></tr>
          <tr><td>422</td><td>Validation error</td></tr>
          <tr><td>429</td><td>Rate limit exceeded (retry after header present)</td></tr>
          <tr><td>500</td><td>Internal server error</td></tr>
        </tbody>
      </table>
    </>
  );
}
