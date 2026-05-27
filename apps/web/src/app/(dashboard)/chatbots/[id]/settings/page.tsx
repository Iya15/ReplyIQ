'use client';

import { use, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Loader2, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { useChatbot, useUpdateChatbot, useDeleteChatbot } from '@/hooks/use-chatbots';

const LANGUAGE_OPTIONS = [
  { value: 'en', label: 'English' },
  { value: 'es', label: 'Spanish' },
  { value: 'fr', label: 'French' },
  { value: 'de', label: 'German' },
  { value: 'pt', label: 'Portuguese' },
  { value: 'ja', label: 'Japanese' },
  { value: 'zh', label: 'Chinese' },
];

const STATUS_OPTIONS = ['draft', 'active', 'paused'] as const;

const schema = z.object({
  name: z.string().min(1, 'Name is required').max(100),
  status: z.enum(STATUS_OPTIONS),
  language: z.string().min(1),
});

type FormValues = z.infer<typeof schema>;

interface PageProps {
  params: Promise<{ id: string }>;
}

export default function ChatbotSettingsPage({ params }: PageProps) {
  const { id } = use(params);
  const router = useRouter();
  const { data: chatbot, isLoading } = useChatbot(id);
  const update = useUpdateChatbot(id);
  const deleteChatbot = useDeleteChatbot();

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: '', status: 'draft', language: 'en' },
  });

  useEffect(() => {
    if (chatbot) {
      form.reset({
        name: chatbot.name,
        status: chatbot.status,
        language: chatbot.language,
      });
    }
  }, [chatbot, form]);

  async function onSubmit(values: FormValues) {
    await update.mutateAsync(values);
  }

  async function handleDelete() {
    if (!confirm(`Permanently delete "${chatbot?.name}"? This cannot be undone.`)) return;
    await deleteChatbot.mutateAsync(id);
    router.push('/chatbots');
  }

  if (isLoading) {
    return (
      <div className="p-6 space-y-4 max-w-xl">
        <Skeleton className="h-8 w-40" />
        <Skeleton className="h-48 w-full" />
        <Skeleton className="h-32 w-full" />
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6 max-w-xl">
      <div>
        <h2 className="text-lg font-semibold">Settings</h2>
        <p className="text-sm text-muted-foreground mt-0.5">
          Manage your chatbot&apos;s name, status, and language.
        </p>
      </div>

      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6" noValidate>
          <Card>
            <CardHeader>
              <CardTitle className="text-sm font-medium">General</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Name</FormLabel>
                    <FormControl>
                      <Input placeholder="Support Assistant" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <div className="grid grid-cols-2 gap-4">
                <FormField
                  control={form.control}
                  name="status"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Status</FormLabel>
                      <FormControl>
                        <select
                          className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm capitalize transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                          {...field}
                        >
                          {STATUS_OPTIONS.map((s) => (
                            <option key={s} value={s} className="capitalize">{s}</option>
                          ))}
                        </select>
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="language"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Language</FormLabel>
                      <FormControl>
                        <select
                          className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                          {...field}
                        >
                          {LANGUAGE_OPTIONS.map((l) => (
                            <option key={l.value} value={l.value}>{l.label}</option>
                          ))}
                        </select>
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
            </CardContent>
          </Card>

          <div className="flex justify-end">
            <Button type="submit" disabled={update.isPending}>
              {update.isPending ? (
                <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Saving…</>
              ) : (
                'Save Changes'
              )}
            </Button>
          </div>
        </form>
      </Form>

      <Separator />

      {/* Danger zone */}
      <Card className="border-destructive/50">
        <CardHeader>
          <CardTitle className="text-sm font-medium text-destructive">Danger Zone</CardTitle>
          <CardDescription className="text-xs">
            Deleting a chatbot is permanent and cannot be undone. The embed code will stop working immediately.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Button
            variant="destructive"
            size="sm"
            onClick={handleDelete}
            disabled={deleteChatbot.isPending}
          >
            {deleteChatbot.isPending ? (
              <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Deleting…</>
            ) : (
              <><Trash2 className="mr-2 h-4 w-4" />Delete Chatbot</>
            )}
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
