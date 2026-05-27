'use client';

import { useState } from 'react';
import { Loader2 } from 'lucide-react';
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
import { useAddTextDocument } from '@/hooks/use-documents';

const MAX_CONTENT = 1_000_000;

interface TextDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  chatbotId: string;
}

export function TextDialog({ open, onOpenChange, chatbotId }: TextDialogProps) {
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [titleError, setTitleError] = useState('');
  const [contentError, setContentError] = useState('');
  const addText = useAddTextDocument(chatbotId);

  function handleClose(nextOpen: boolean) {
    if (addText.isPending) return;
    onOpenChange(nextOpen);
    if (!nextOpen) reset();
  }

  function reset() {
    setTitle('');
    setContent('');
    setTitleError('');
    setContentError('');
    addText.reset();
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setTitleError('');
    setContentError('');

    let valid = true;
    if (!title.trim()) {
      setTitleError('Title is required.');
      valid = false;
    }
    if (!content.trim()) {
      setContentError('Content is required.');
      valid = false;
    }
    if (!valid) return;

    await addText.mutateAsync({ title: title.trim(), content: content.trim() });
    onOpenChange(false);
    reset();
  }

  const remaining = MAX_CONTENT - content.length;

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Add text / FAQ</DialogTitle>
        </DialogHeader>

        <form id="text-doc-form" onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="text-title">Title</Label>
            <Input
              id="text-title"
              placeholder="e.g. Getting Started Guide"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              aria-invalid={!!titleError}
              disabled={addText.isPending}
            />
            {titleError && <p className="text-xs text-destructive">{titleError}</p>}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="text-content">Content</Label>
            <textarea
              id="text-content"
              rows={12}
              maxLength={MAX_CONTENT}
              placeholder="Paste or type your content here…"
              value={content}
              onChange={(e) => setContent(e.target.value)}
              aria-invalid={!!contentError}
              disabled={addText.isPending}
              className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm font-mono shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-y min-h-[240px] disabled:opacity-50"
            />
            <div className="flex items-start justify-between gap-4">
              <p className="text-xs text-muted-foreground">
                {contentError ? (
                  <span className="text-destructive">{contentError}</span>
                ) : (
                  'Tip: format as FAQs (Q: … / A: …) for better answers.'
                )}
              </p>
              <p
                className={`text-xs shrink-0 tabular-nums ${
                  remaining < 10_000 ? 'text-amber-500' : 'text-muted-foreground'
                }`}
              >
                {remaining.toLocaleString()} left
              </p>
            </div>
          </div>
        </form>

        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => handleClose(false)}
            disabled={addText.isPending}
          >
            Cancel
          </Button>
          <Button type="submit" form="text-doc-form" disabled={addText.isPending}>
            {addText.isPending ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Saving…
              </>
            ) : (
              'Add document'
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
