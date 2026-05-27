'use client';

import { formatDistanceToNow } from 'date-fns';
import { File, FileText, FileType, Globe, MessageSquare, MoreHorizontal, RefreshCw, Trash2 } from 'lucide-react';
import type { Document, DocumentSourceType, DocumentStatus } from '@replyiq/api-client';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { useDeleteDocument, useReprocessDocument } from '@/hooks/use-documents';
import { cn } from '@/lib/utils';

interface DocumentRowProps {
  document: Document;
  chatbotId: string;
}

const SOURCE_META: Record<DocumentSourceType, { label: string; Icon: React.FC<{ className?: string }> }> = {
  pdf:    { label: 'PDF',    Icon: FileText },
  docx:   { label: 'DOCX',  Icon: FileType },
  txt:    { label: 'TXT',   Icon: File },
  url:    { label: 'URL',   Icon: Globe },
  manual: { label: 'Manual', Icon: MessageSquare },
  faq:    { label: 'FAQ',   Icon: MessageSquare },
};

function StatusBadge({
  status,
  errorMessage,
}: {
  status: DocumentStatus;
  errorMessage: string | null;
}) {
  const base = 'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium';

  switch (status) {
    case 'ready':
      return (
        <span className={cn(base, 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400')}>
          Ready
        </span>
      );
    case 'failed':
      return (
        <span
          title={errorMessage ?? undefined}
          className={cn(base, 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 cursor-default')}
        >
          <span className="h-1.5 w-1.5 rounded-full bg-red-500 shrink-0" />
          Failed
        </span>
      );
    case 'processing':
      return (
        <span className={cn(base, 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400')}>
          <span className="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse shrink-0" />
          Processing
        </span>
      );
    default: // pending
      return (
        <span className={cn(base, 'bg-muted text-muted-foreground')}>
          <span className="h-1.5 w-1.5 rounded-full bg-muted-foreground/40 shrink-0" />
          Pending
        </span>
      );
  }
}

export function DocumentRow({ document: doc, chatbotId }: DocumentRowProps) {
  const deleteMutation = useDeleteDocument(chatbotId);
  const reprocessMutation = useReprocessDocument(chatbotId);
  const { label, Icon } = SOURCE_META[doc.source_type] ?? SOURCE_META.manual;
  const isOptimistic = doc.id.startsWith('optimistic-');

  return (
    <tr className={cn('border-b last:border-0 transition-colors hover:bg-muted/30', isOptimistic && 'opacity-60')}>
      <td className="px-4 py-3 text-sm font-medium max-w-[240px]">
        <span className="block truncate">{doc.title || 'Untitled'}</span>
      </td>
      <td className="px-4 py-3">
        <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
          <Icon className="h-3.5 w-3.5 shrink-0" />
          {label}
        </span>
      </td>
      <td className="px-4 py-3">
        <StatusBadge status={doc.status} errorMessage={doc.error_message} />
      </td>
      <td className="px-4 py-3 text-sm text-muted-foreground tabular-nums">
        {doc.chunk_count ?? '—'}
      </td>
      <td className="px-4 py-3 text-sm text-muted-foreground whitespace-nowrap">
        {formatDistanceToNow(new Date(doc.created_at), { addSuffix: true })}
      </td>
      <td className="px-4 py-3">
        {!isOptimistic && (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-8 w-8">
                <span className="sr-only">Open menu</span>
                <MoreHorizontal className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {doc.status === 'failed' && (
                <DropdownMenuItem
                  onClick={() => reprocessMutation.mutate(doc.id)}
                  disabled={reprocessMutation.isPending}
                >
                  <RefreshCw className="mr-2 h-3.5 w-3.5" />
                  Reprocess
                </DropdownMenuItem>
              )}
              <DropdownMenuItem
                className="text-destructive focus:text-destructive"
                onClick={() => deleteMutation.mutate(doc.id)}
                disabled={deleteMutation.isPending}
              >
                <Trash2 className="mr-2 h-3.5 w-3.5" />
                Delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        )}
      </td>
    </tr>
  );
}
