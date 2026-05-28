import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import './styles/widget.css';

/**
 * Iframe app entry point.
 *
 * The widget iframe URL is: https://cdn.replyiq.com/widget/{chatbotId}
 * We read the chatbot public_id from the last URL path segment.
 *
 * Example:
 *   https://cdn.replyiq.com/widget/abc-123  →  chatbotId = 'abc-123'
 */
const segments = window.location.pathname.split('/').filter((s) => s.length > 0);
const chatbotId = segments[segments.length - 1] ?? '';

const rootEl = document.getElementById('root');

if (rootEl) {
  ReactDOM.createRoot(rootEl).render(
    <React.StrictMode>
      <App chatbotId={chatbotId} />
    </React.StrictMode>,
  );
}
