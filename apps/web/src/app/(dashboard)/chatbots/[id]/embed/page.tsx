'use client';

import { use, useState } from 'react';
import { Copy, Check, Plus, X } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useChatbot, useUpdateChatbotSettings } from '@/hooks/use-chatbots';

interface PageProps {
  params: Promise<{ id: string }>;
}

export default function EmbedPage({ params }: PageProps) {
  const { id } = use(params);
  const { data: chatbot, isLoading } = useChatbot(id);
  const updateSettings = useUpdateChatbotSettings(id);
  const [copied, setCopied] = useState(false);
  const [newDomain, setNewDomain] = useState('');

  const embedCode = chatbot
    ? `<script src="${process.env.NEXT_PUBLIC_API_URL?.replace('/api/v1', '') ?? 'https://api.replyiq.app'}/widget.js" data-chatbot="${chatbot.public_id}" async></script>`
    : '';

  const allowedDomains = chatbot?.settings?.allowed_domains ?? [];

  function copyCode() {
    if (!embedCode) return;
    navigator.clipboard.writeText(embedCode);
    setCopied(true);
    toast.success('Embed code copied!');
    setTimeout(() => setCopied(false), 2000);
  }

  function addDomain() {
    const domain = newDomain.trim().toLowerCase();
    if (!domain) return;
    if (allowedDomains.includes(domain)) {
      toast.error('Domain already added');
      return;
    }
    updateSettings.mutate({ allowed_domains: [...allowedDomains, domain] });
    setNewDomain('');
  }

  function removeDomain(domain: string) {
    updateSettings.mutate({ allowed_domains: allowedDomains.filter((d) => d !== domain) });
  }

  if (isLoading) {
    return (
      <div className="p-6 space-y-4">
        <Skeleton className="h-8 w-40" />
        <Skeleton className="h-48 w-full" />
        <Skeleton className="h-48 w-full" />
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6 max-w-2xl">
      <div>
        <h2 className="text-lg font-semibold">Embed</h2>
        <p className="text-sm text-muted-foreground mt-0.5">
          Add the widget to your website by pasting this snippet before the closing{' '}
          <code className="text-xs bg-muted px-1 rounded">&lt;/body&gt;</code> tag.
        </p>
      </div>

      {/* Embed code */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle className="text-sm font-medium">Embed Code</CardTitle>
            <Button size="sm" variant="outline" onClick={copyCode} disabled={!embedCode}>
              {copied ? (
                <><Check className="mr-1.5 h-3.5 w-3.5 text-emerald-500" />Copied</>
              ) : (
                <><Copy className="mr-1.5 h-3.5 w-3.5" />Copy</>
              )}
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <pre className="bg-muted rounded-lg p-4 text-xs font-mono overflow-x-auto whitespace-pre-wrap break-all select-all">
            {embedCode || '—'}
          </pre>
        </CardContent>
      </Card>

      {/* Allowed domains */}
      <Card>
        <CardHeader>
          <CardTitle className="text-sm font-medium">Allowed Domains</CardTitle>
          <CardDescription className="text-xs">
            Leave empty to allow all domains. Add domains to restrict where the widget can be embedded.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="flex gap-2">
            <Input
              placeholder="example.com"
              value={newDomain}
              onChange={(e) => setNewDomain(e.target.value)}
              onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addDomain(); } }}
            />
            <Button
              type="button"
              variant="outline"
              size="icon"
              onClick={addDomain}
              disabled={!newDomain.trim() || updateSettings.isPending}
            >
              <Plus className="h-4 w-4" />
            </Button>
          </div>

          {allowedDomains.length === 0 ? (
            <p className="text-xs text-muted-foreground">
              All domains allowed — add a domain to restrict access.
            </p>
          ) : (
            <ul className="space-y-1.5">
              {allowedDomains.map((domain) => (
                <li
                  key={domain}
                  className="flex items-center justify-between rounded-md border px-3 py-1.5"
                >
                  <span className="text-sm font-mono">{domain}</span>
                  <button
                    type="button"
                    onClick={() => removeDomain(domain)}
                    disabled={updateSettings.isPending}
                    className="text-muted-foreground hover:text-destructive transition-colors ml-2"
                    aria-label={`Remove ${domain}`}
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
