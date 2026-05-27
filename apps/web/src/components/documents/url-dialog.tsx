'use client';

import { useState } from 'react';
import { Info, Loader2 } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAddUrlDocument } from '@/hooks/use-documents';

const URL_REGEX = /^https?:\/\/.+/;
const MAX_PAGES = 50;
const DEFAULT_PAGES = 10;

interface UrlDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  chatbotId: string;
}

export function UrlDialog({ open, onOpenChange, chatbotId }: UrlDialogProps) {
  const [url, setUrl] = useState('');
  const [maxPages, setMaxPages] = useState(DEFAULT_PAGES);
  const [entireSite, setEntireSite] = useState(true);
  const [urlError, setUrlError] = useState('');
  const addUrl = useAddUrlDocument(chatbotId);

  function handleClose(nextOpen: boolean) {
    if (addUrl.isPending) return;
    onOpenChange(nextOpen);
    if (!nextOpen) reset();
  }

  function reset() {
    setUrl('');
    setMaxPages(DEFAULT_PAGES);
    setEntireSite(true);
    setUrlError('');
    addUrl.reset();
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setUrlError('');

    const trimmed = url.trim();
    if (!trimmed) {
      setUrlError('URL is required.');
      return;
    }
    if (!URL_REGEX.test(trimmed)) {
      setUrlError('Must start with https:// or http://');
      return;
    }

    await addUrl.mutateAsync({ url: trimmed, max_pages: entireSite ? maxPages : 1 });
    onOpenChange(false);
    reset();
  }

  const effectivePages = entireSite ? maxPages : 1;

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Add website</DialogTitle>
        </DialogHeader>

        <form id="url-doc-form" onSubmit={handleSubmit} className="space-y-5">
          {/* URL input */}
          <div className="space-y-1.5">
            <Label htmlFor="crawl-url">Website URL</Label>
            <Input
              id="crawl-url"
              type="url"
              placeholder="https://example.com"
              value={url}
              onChange={(e) => {
                setUrl(e.target.value);
                if (urlError) setUrlError('');
              }}
              aria-invalid={!!urlError}
              disabled={addUrl.isPending}
              autoFocus
            />
            {urlError && <p className="text-xs text-destructive">{urlError}</p>}
          </div>

          {/* Crawl scope toggle */}
          <div className="space-y-1.5">
            <Label>Crawl scope</Label>
            <div className="grid grid-cols-2 gap-2">
              {[
                { value: true,  label: 'Entire site' },
                { value: false, label: 'Single page only' },
              ].map(({ value, label }) => (
                <button
                  key={label}
                  type="button"
                  onClick={() => setEntireSite(value)}
                  disabled={addUrl.isPending}
                  className={[
                    'rounded-md border px-3 py-2 text-sm text-left transition-colors',
                    'disabled:cursor-not-allowed disabled:opacity-50',
                    entireSite === value
                      ? 'border-primary bg-primary/5 text-foreground font-medium'
                      : 'border-input text-muted-foreground hover:border-ring hover:text-foreground',
                  ].join(' ')}
                >
                  {label}
                </button>
              ))}
            </div>
          </div>

          {/* Max pages slider — hidden when single page */}
          {entireSite && (
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <Label htmlFor="max-pages">Max pages</Label>
                <span className="text-sm font-medium tabular-nums">{maxPages}</span>
              </div>
              <input
                id="max-pages"
                type="range"
                min={1}
                max={MAX_PAGES}
                step={1}
                value={maxPages}
                onChange={(e) => setMaxPages(Number(e.target.value))}
                disabled={addUrl.isPending}
                className="h-1.5 w-full cursor-pointer accent-primary disabled:cursor-not-allowed disabled:opacity-50"
              />
              <div className="flex justify-between text-xs text-muted-foreground select-none">
                <span>1</span>
                <span>{MAX_PAGES}</span>
              </div>
            </div>
          )}

          {/* Contextual help text */}
          <div className="flex gap-2 rounded-md bg-muted/50 px-3 py-2.5 text-xs text-muted-foreground">
            <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
            <p>
              {entireSite
                ? `ReplyIQ will visit up to ${effectivePages} page${effectivePages !== 1 ? 's' : ''} on
                   that domain, extract the main content from each, and make it searchable in your chatbot.
                   JavaScript-heavy pages may return limited content.`
                : 'ReplyIQ will index only the content of this exact URL. Links on the page are not followed.'}
            </p>
          </div>
        </form>

        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => handleClose(false)}
            disabled={addUrl.isPending}
          >
            Cancel
          </Button>
          <Button type="submit" form="url-doc-form" disabled={addUrl.isPending}>
            {addUrl.isPending ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Starting crawl…
              </>
            ) : (
              'Start crawling →'
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
