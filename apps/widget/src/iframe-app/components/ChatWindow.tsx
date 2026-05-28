import ChatHeader from './ChatHeader';
import MessageInput from './MessageInput';
import MessageList from './MessageList';
import { useConversation } from '../hooks/useConversation';
import type { ChatbotConfig, Session } from '../lib/types';

interface Props {
  config:  ChatbotConfig;
  session: Session | null;
}

function close() {
  window.parent.postMessage({ type: 'riq:close' }, '*');
}

export default function ChatWindow({ config, session }: Props) {
  const {
    messages,
    sendMessage,
    sendFeedback,
    escalate,
    isPending,
    isLoading,
    isEscalated,
    agentName,
    shouldSuggestEscalation,
    errorMessage,
  } = useConversation(session);

  return (
    <div
      className="flex h-full flex-col overflow-hidden"
      style={{ background: 'var(--riq-bg)', color: 'var(--riq-text)' }}
    >
      <ChatHeader config={config} onClose={close} />

      <MessageList
        config={config}
        messages={messages}
        isLoading={isLoading}
        onFeedback={sendFeedback}
      />

      {/* Agent joined banner */}
      {isEscalated && (
        <div
          style={{
            borderTop: '1px solid rgba(59,130,246,0.25)',
            background: 'rgba(59,130,246,0.07)',
            padding: '8px 16px',
            textAlign: 'center',
            fontSize: '12px',
            color: '#2563eb',
          }}
          role="status"
        >
          {agentName
            ? `${agentName} has joined the conversation`
            : 'An agent has joined the conversation'}
        </div>
      )}

      {/* Escalation suggestion (after consecutive AI failures) */}
      {!isEscalated && shouldSuggestEscalation && session && (
        <div
          style={{
            borderTop: '1px solid rgba(234,179,8,0.25)',
            background: 'rgba(234,179,8,0.07)',
            padding: '8px 16px',
            textAlign: 'center',
            fontSize: '12px',
            color: '#b45309',
          }}
        >
          Having trouble?{' '}
          <button
            onClick={escalate}
            style={{ fontWeight: 600, textDecoration: 'underline', cursor: 'pointer', background: 'none', border: 'none', color: 'inherit', fontSize: 'inherit', padding: 0 }}
          >
            Talk to a person
          </button>
        </div>
      )}

      {/* Rate-limit / send error */}
      {errorMessage && (
        <div
          style={{
            borderTop: '1px solid rgba(217,119,6,0.25)',
            background: 'rgba(217,119,6,0.07)',
            padding: '6px 16px',
            textAlign: 'center',
            fontSize: '12px',
            color: '#b45309',
          }}
          role="alert"
        >
          {errorMessage}
        </div>
      )}

      <MessageInput
        config={config}
        onSend={sendMessage}
        disabled={isPending || !session}
        isEscalated={isEscalated}
        {...(session !== null ? { onEscalate: () => { void escalate(); } } : {})}
      />
    </div>
  );
}
