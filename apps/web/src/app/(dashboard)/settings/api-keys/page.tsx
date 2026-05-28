'use client';

import { useState } from 'react';
import { format } from 'date-fns';
import { Plus, Copy, Check, Trash2, AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import { useApiKeys, useCreateApiKey, useRevokeApiKey } from '@/hooks/use-api-keys';
import type { ApiKey } from '@replyiq/api-client';

// ── Copy button with brief "copied" state ─────────────────────────────────────

function CopyButton({ text }: { text: string }) {
  const [copied, setCopied] = useState(false);

  function copy() {
    navigator.clipboard.writeText(text).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  }

  return (
    <button
      onClick={copy}
      className="flex items-center gap-1 rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted transition-colors"
      title="Copy to clipboard"
    >
      {copied ? <Check className="h-3.5 w-3.5 text-green-600" /> : <Copy className="h-3.5 w-3.5" />}
      {copied ? 'Copied' : 'Copy'}
    </button>
  );
}

// ── Create key dialog ─────────────────────────────────────────────────────────

type CreateDialogState =
  | { phase: 'form' }
  | { phase: 'reveal'; key: string; name: string };

function CreateDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [state, setState] = useState<CreateDialogState>({ phase: 'form' });
  const [name, setName]   = useState('');
  const createKey         = useCreateApiKey();

  function handleClose() {
    setState({ phase: 'form' });
    setName('');
    onClose();
  }

  function handleCreate(e: React.FormEvent) {
    e.preventDefault();
    createKey.mutate({ name }, {
      onSuccess: (res) => {
        const key = res.data.key ?? '';
        setState({ phase: 'reveal', key, name });
      },
    });
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="max-w-md">
        {state.phase === 'form' ? (
          <>
            <DialogHeader>
              <DialogTitle>Create API key</DialogTitle>
            </DialogHeader>
            <form onSubmit={handleCreate} className="space-y-4 pt-2">
              <div className="space-y-1.5">
                <Label htmlFor="key-name">Key name</Label>
                <Input
                  id="key-name"
                  placeholder="e.g. Production, CI/CD, Testing"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  required
                  autoFocus
                />
              </div>
              <DialogFooter>
                <Button type="button" variant="outline" onClick={handleClose}>Cancel</Button>
                <Button type="submit" disabled={createKey.isPending}>
                  {createKey.isPending ? 'Creating…' : 'Create key'}
                </Button>
              </DialogFooter>
            </form>
          </>
        ) : (
          <>
            <DialogHeader>
              <DialogTitle>Save your API key</DialogTitle>
            </DialogHeader>
            <div className="space-y-4 pt-2">
              <div className="flex items-start gap-2.5 rounded-lg border border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/30 px-4 py-3">
                <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                <p className="text-sm text-amber-800 dark:text-amber-300">
                  <strong>Save this key now.</strong> It will never be shown again.
                </p>
              </div>

              <div className="space-y-1.5">
                <Label>Your new API key</Label>
                <div className="flex items-center gap-2">
                  <Input
                    readOnly
                    value={state.key}
                    className="font-mono text-xs"
                    onClick={(e) => (e.target as HTMLInputElement).select()}
                  />
                  <CopyButton text={state.key} />
                </div>
              </div>

              <DialogFooter>
                <Button onClick={handleClose}>Done</Button>
              </DialogFooter>
            </div>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}

// ── Revoke confirmation ───────────────────────────────────────────────────────

function RevokeConfirm({
  apiKey,
  onClose,
}: {
  apiKey: ApiKey | null;
  onClose: () => void;
}) {
  const revokeKey = useRevokeApiKey();

  return (
    <Dialog open={!!apiKey} onOpenChange={onClose}>
      <DialogContent className="max-w-sm">
        <DialogHeader>
          <DialogTitle>Revoke &ldquo;{apiKey?.name}&rdquo;?</DialogTitle>
        </DialogHeader>
        <p className="text-sm text-muted-foreground">
          Any integrations using this key will stop working immediately.
        </p>
        <DialogFooter className="pt-2">
          <Button variant="outline" onClick={onClose}>Cancel</Button>
          <Button
            variant="destructive"
            disabled={revokeKey.isPending}
            onClick={() => apiKey && revokeKey.mutate(apiKey.id, { onSuccess: onClose })}
          >
            {revokeKey.isPending ? 'Revoking…' : 'Revoke key'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default function ApiKeysPage() {
  const [createOpen, setCreateOpen]     = useState(false);
  const [revokeTarget, setRevokeTarget] = useState<ApiKey | null>(null);

  const { data, isLoading } = useApiKeys();
  const keys = data?.data ?? [];

  return (
    <div className="p-6 max-w-3xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">API Keys</h1>
          <p className="text-sm text-muted-foreground mt-0.5">
            Authenticate programmatic requests to the ReplyIQ API.
          </p>
        </div>
        <Button onClick={() => setCreateOpen(true)}>
          <Plus className="mr-2 h-4 w-4" />
          Create key
        </Button>
      </div>

      {/* Keys table */}
      {isLoading ? (
        <div className="space-y-2">
          {Array.from({ length: 2 }).map((_, i) => (
            <Skeleton key={i} className="h-14 rounded-xl" />
          ))}
        </div>
      ) : keys.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-20 text-center rounded-xl border">
          <p className="text-base font-medium">No API keys yet</p>
          <p className="text-sm text-muted-foreground mt-1">
            Create a key to start building integrations.
          </p>
          <Button className="mt-4" onClick={() => setCreateOpen(true)}>
            <Plus className="mr-2 h-4 w-4" />
            Create your first key
          </Button>
        </div>
      ) : (
        <div className="rounded-xl border overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted/40">
                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">Name</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide">Key</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide hidden sm:table-cell">Last used</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wide hidden md:table-cell">Created</th>
                <th className="px-4 py-3 w-10" />
              </tr>
            </thead>
            <tbody>
              {keys.map((key) => (
                <tr key={key.id} className="border-b last:border-0 hover:bg-muted/20 transition-colors">
                  <td className="px-4 py-3 font-medium">{key.name}</td>
                  <td className="px-4 py-3">
                    <code className="rounded bg-muted px-2 py-0.5 text-xs font-mono">
                      {key.prefix}…
                    </code>
                  </td>
                  <td className="px-4 py-3 text-muted-foreground hidden sm:table-cell">
                    {key.last_used_at ? format(new Date(key.last_used_at), 'MMM d, yyyy') : 'Never'}
                  </td>
                  <td className="px-4 py-3 text-muted-foreground hidden md:table-cell">
                    {format(new Date(key.created_at), 'MMM d, yyyy')}
                  </td>
                  <td className="px-4 py-3">
                    <Button
                      size="icon"
                      variant="ghost"
                      className="h-7 w-7 text-muted-foreground hover:text-destructive"
                      onClick={() => setRevokeTarget(key)}
                      title="Revoke key"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <CreateDialog open={createOpen} onClose={() => setCreateOpen(false)} />
      <RevokeConfirm apiKey={revokeTarget} onClose={() => setRevokeTarget(null)} />
    </div>
  );
}
