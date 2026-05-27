import { MessageSquare } from 'lucide-react';

export default function ConversationsPage() {
  return (
    <div className="flex flex-col items-center justify-center h-full min-h-[400px] text-center p-6">
      <div className="flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 mb-4">
        <MessageSquare className="h-7 w-7 text-primary" />
      </div>
      <h2 className="text-lg font-semibold">Conversations</h2>
      <p className="text-sm text-muted-foreground mt-1 max-w-xs">
        View and search all conversations with your chatbot. Coming in Phase 2.
      </p>
    </div>
  );
}
