'use client';

import { use, useEffect, useState } from 'react';
import { FileText, FileUp, Globe, Plus, Type } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useDocuments } from '@/hooks/use-documents';
import { UploadDialog } from '@/components/documents/upload-dialog';
import { TextDialog } from '@/components/documents/text-dialog';
import { UrlDialog } from '@/components/documents/url-dialog';
import { DocumentRow } from '@/components/documents/document-row';
import type { DocumentStatus } from '@replyiq/api-client';

const STATUS_TABS: Array<{ label: string; value: DocumentStatus | undefined }> = [
  { label: 'All',     value: undefined },
  { label: 'Pending', value: 'pending' },
  { label: 'Ready',   value: 'ready' },
  { label: 'Failed',  value: 'failed' },
];

interface PageProps {
  params: Promise<{ id: string }>;
}

export default function KnowledgePage({ params }: PageProps) {
  const { id } = use(params);
  const [statusFilter, setStatusFilter] = useState<DocumentStatus | undefined>(undefined);
  const [uploadOpen, setUploadOpen] = useState(false);
  const [textOpen, setTextOpen] = useState(false);
  const [urlOpen, setUrlOpen] = useState(false);

  const { data, isLoading } = useDocuments(id, statusFilter ? { status: statusFilter } : undefined);

  // Keyboard shortcuts: u → upload, n → text, w → website (ignored when focus is in an input).
  useEffect(() => {
    function onKeyDown(e: KeyboardEvent) {
      const tag = (e.target as HTMLElement).tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || (e.target as HTMLElement).isContentEditable) return;
      if (e.metaKey || e.ctrlKey || e.altKey || e.shiftKey) return;

      if (e.key === 'u') { e.preventDefault(); setUploadOpen(true); }
      if (e.key === 'n') { e.preventDefault(); setTextOpen(true); }
      if (e.key === 'w') { e.preventDefault(); setUrlOpen(true); }
    }

    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, []);
  const documents = data?.data ?? [];
  const total = data?.meta.total ?? 0;

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4">
        <div>
          <h2 className="text-lg font-semibold">Knowledge Base</h2>
          <p className="text-sm text-muted-foreground mt-0.5">
            {isLoading ? (
              <Skeleton className="h-4 w-28 inline-block" />
            ) : (
              `${total} document${total !== 1 ? 's' : ''}`
            )}
          </p>
        </div>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              Add knowledge
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-52">
            <DropdownMenuItem onClick={() => setUploadOpen(true)}>
              <FileUp className="mr-2 h-4 w-4" />
              Upload file
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => setTextOpen(true)}>
              <Type className="mr-2 h-4 w-4" />
              Add text / FAQ
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => setUrlOpen(true)}>
              <Globe className="mr-2 h-4 w-4" />
              Crawl website
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      {/* Status filter tabs */}
      <div className="flex border-b">
        {STATUS_TABS.map((tab) => (
          <button
            key={tab.label}
            onClick={() => setStatusFilter(tab.value)}
            className={`px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px ${
              statusFilter === tab.value
                ? 'border-primary text-foreground'
                : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Content */}
      {isLoading ? (
        <div className="space-y-2">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-12 w-full rounded-lg" />
          ))}
        </div>
      ) : documents.length === 0 ? (
        <EmptyState
          hasFilter={statusFilter !== undefined}
          onUpload={() => setUploadOpen(true)}
          onText={() => setTextOpen(true)}
        />
      ) : (
        <div className="rounded-lg border overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted/50">
                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">
                  Title
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">
                  Type
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">
                  Status
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">
                  Chunks
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">
                  Created
                </th>
                <th className="px-4 py-3 w-10" />
              </tr>
            </thead>
            <tbody>
              {documents.map((doc) => (
                <DocumentRow key={doc.id} document={doc} chatbotId={id} />
              ))}
            </tbody>
          </table>
        </div>
      )}

      <UploadDialog open={uploadOpen} onOpenChange={setUploadOpen} chatbotId={id} />
      <TextDialog open={textOpen} onOpenChange={setTextOpen} chatbotId={id} />
      <UrlDialog open={urlOpen} onOpenChange={setUrlOpen} chatbotId={id} />
    </div>
  );
}

function EmptyState({
  hasFilter,
  onUpload,
  onText,
}: {
  hasFilter: boolean;
  onUpload: () => void;
  onText: () => void;
}) {
  if (hasFilter) {
    return (
      <div className="flex flex-col items-center justify-center py-24 text-center">
        <p className="text-sm text-muted-foreground">No documents match this filter.</p>
      </div>
    );
  }

  return (
    <div className="flex flex-col items-center justify-center py-24 text-center gap-4">
      <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-muted">
        <FileText className="h-8 w-8 text-muted-foreground" />
      </div>
      <div>
        <p className="text-base font-medium">No knowledge yet</p>
        <p className="text-sm text-muted-foreground mt-1">
          Upload a file or add text to start training your chatbot.
        </p>
      </div>
      <div className="flex gap-2">
        <Button onClick={onUpload}>
          <FileUp className="mr-2 h-4 w-4" />
          Upload file
        </Button>
        <Button variant="outline" onClick={onText}>
          <Type className="mr-2 h-4 w-4" />
          Add text
        </Button>
      </div>
    </div>
  );
}
