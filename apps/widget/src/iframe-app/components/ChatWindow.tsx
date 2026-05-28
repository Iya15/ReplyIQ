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
  const { messages, sendMessage, sendFeedback, isPending, isLoading } =
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

      <MessageInput
        config={config}
        onSend={sendMessage}
        disabled={isPending || !session}
      />
    </div>
  );
}
