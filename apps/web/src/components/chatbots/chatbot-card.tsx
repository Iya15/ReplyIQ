'use client';

import Link from 'next/link';
import { Bot, MoreHorizontal, Pause, Play, Trash2 } from 'lucide-react';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useUpdateChatbot, useDeleteChatbot } from '@/hooks/use-chatbots';
import type { Chatbot } from '@replyiq/api-client';

const STATUS_STYLES: Record<Chatbot['status'], string> = {
  active: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
  draft: 'bg-zinc-500/15 text-zinc-600 dark:text-zinc-400',
  paused: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
};

interface ChatbotCardProps {
  chatbot: Chatbot;
}

export function ChatbotCard({ chatbot }: ChatbotCardProps) {
  const update = useUpdateChatbot(chatbot.id);
  const deleteChatbot = useDeleteChatbot();

  function toggleStatus() {
    const next = chatbot.status === 'active' ? 'paused' : 'active';
    update.mutate({ status: next });
  }

  function handleDelete() {
    if (!confirm(`Delete "${chatbot.name}"? This cannot be undone.`)) return;
    deleteChatbot.mutate(chatbot.id);
  }

  return (
    <Card className="flex flex-col hover:shadow-md transition-shadow">
      <CardContent className="flex-1 pt-6">
        <div className="flex items-start justify-between gap-2">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 shrink-0">
              <Bot className="h-5 w-5 text-primary" />
            </div>
            <div className="min-w-0">
              <Link
                href={`/chatbots/${chatbot.id}`}
                className="font-medium hover:underline line-clamp-1"
              >
                {chatbot.name}
              </Link>
              <p className="text-xs text-muted-foreground mt-0.5">{chatbot.language.toUpperCase()}</p>
            </div>
          </div>

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-8 w-8 shrink-0">
                <MoreHorizontal className="h-4 w-4" />
                <span className="sr-only">Open menu</span>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem asChild>
                <Link href={`/chatbots/${chatbot.id}`}>View details</Link>
              </DropdownMenuItem>
              <DropdownMenuItem asChild>
                <Link href={`/chatbots/${chatbot.id}/settings`}>Settings</Link>
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem
                onClick={toggleStatus}
                disabled={update.isPending}
              >
                {chatbot.status === 'active' ? (
                  <><Pause className="mr-2 h-4 w-4" /> Pause</>
                ) : (
                  <><Play className="mr-2 h-4 w-4" /> Activate</>
                )}
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem
                className="text-destructive focus:text-destructive"
                onClick={handleDelete}
                disabled={deleteChatbot.isPending}
              >
                <Trash2 className="mr-2 h-4 w-4" />
                Delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </CardContent>

      <CardFooter className="pt-0 pb-4 px-6 flex items-center justify-between">
        <span
          className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ${STATUS_STYLES[chatbot.status]}`}
        >
          {chatbot.status}
        </span>
        <span className="text-xs text-muted-foreground">
          {new Date(chatbot.created_at).toLocaleDateString()}
        </span>
      </CardFooter>
    </Card>
  );
}
