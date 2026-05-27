'use client';

import { useState } from 'react';
import { Plus, Bot } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { ChatbotCard } from '@/components/chatbots/chatbot-card';
import { NewChatbotDialog } from '@/components/chatbots/new-chatbot-dialog';
import { useChatbots } from '@/hooks/use-chatbots';

export default function ChatbotsPage() {
  const [dialogOpen, setDialogOpen] = useState(false);
  const { data, isLoading } = useChatbots();

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Chatbots</h1>
          <p className="text-sm text-muted-foreground mt-1">
            Build and manage your AI chatbots.
          </p>
        </div>
        <Button onClick={() => setDialogOpen(true)}>
          <Plus className="mr-2 h-4 w-4" />
          New Chatbot
        </Button>
      </div>

      {isLoading ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-36 rounded-xl" />
          ))}
        </div>
      ) : !data?.length ? (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
          <div className="flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 mb-4">
            <Bot className="h-7 w-7 text-primary" />
          </div>
          <h2 className="text-lg font-medium">No chatbots yet</h2>
          <p className="text-sm text-muted-foreground mt-1 mb-6 max-w-xs">
            Create your first chatbot and start answering customer questions automatically.
          </p>
          <Button onClick={() => setDialogOpen(true)}>
            <Plus className="mr-2 h-4 w-4" />
            New Chatbot
          </Button>
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {data.map((chatbot) => (
            <ChatbotCard key={chatbot.id} chatbot={chatbot} />
          ))}
        </div>
      )}

      <NewChatbotDialog open={dialogOpen} onOpenChange={setDialogOpen} />
    </div>
  );
}
