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
  const { messages, sendMessage, sendFeedback, isPending, isLoading, errorMessage } =
    useConversation(session);

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
      />
    </div>
  );
}
