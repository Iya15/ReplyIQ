'use client';

import { use, useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { HexColorPicker } from 'react-colorful';
import { Loader2, Bot } from 'lucide-react';
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
import { Skeleton } from '@/components/ui/skeleton';
import { useChatbot, useUpdateChatbotSettings } from '@/hooks/use-chatbots';

const schema = z.object({
  welcome_message: z.string().min(1, 'Required'),
  placeholder_text: z.string().nullable(),
  primary_color: z.string().regex(/^#[0-9A-Fa-f]{6}$/, 'Must be a hex color'),
  text_color: z.string().regex(/^#[0-9A-Fa-f]{6}$/, 'Must be a hex color'),
  font_family: z.string().min(1, 'Required'),
  ai_tone: z.enum(['professional', 'friendly', 'casual', 'formal']),
  ai_persona: z.string().nullable(),
  position: z.enum(['bottom-right', 'bottom-left', 'top-right', 'top-left']),
  theme: z.enum(['light', 'dark', 'auto']),
  show_branding: z.boolean(),
});

type FormValues = z.infer<typeof schema>;

const FONT_OPTIONS = ['Inter', 'Roboto', 'Open Sans', 'Poppins', 'Lato'];
const TONE_OPTIONS = ['professional', 'friendly', 'casual', 'formal'] as const;
const POSITION_OPTIONS = ['bottom-right', 'bottom-left', 'top-right', 'top-left'] as const;
const THEME_OPTIONS = ['light', 'dark', 'auto'] as const;

interface PageProps {
  params: Promise<{ id: string }>;
}

export default function CustomizePage({ params }: PageProps) {
  const { id } = use(params);
  const { data: chatbot, isLoading } = useChatbot(id);
  const update = useUpdateChatbotSettings(id);
  const [primaryPicker, setPrimaryPicker] = useState(false);
  const [textPicker, setTextPicker] = useState(false);

  const settings = chatbot?.settings;

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      welcome_message: '',
      placeholder_text: 'Ask me anything...',
      primary_color: '#4F46E5',
      text_color: '#0F172A',
      font_family: 'Inter',
      ai_tone: 'professional',
      ai_persona: null,
      position: 'bottom-right',
      theme: 'auto',
      show_branding: true,
    },
  });

  useEffect(() => {
    if (settings) {
      form.reset({
        welcome_message: settings.welcome_message,
        placeholder_text: settings.placeholder_text,
        primary_color: settings.primary_color,
        text_color: settings.text_color,
        font_family: settings.font_family,
        ai_tone: settings.ai_tone,
        ai_persona: settings.ai_persona,
        position: settings.position,
        theme: settings.theme,
        show_branding: settings.show_branding,
      });
    }
  }, [settings, form]);

  const watchedValues = form.watch();

  async function onSubmit(values: FormValues) {
    await update.mutateAsync(values);
  }

  if (isLoading) {
    return (
      <div className="p-6 space-y-4">
        <Skeleton className="h-8 w-48" />
        <div className="grid lg:grid-cols-2 gap-6">
          <Skeleton className="h-96" />
          <Skeleton className="h-96" />
        </div>
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6">
      <div>
        <h2 className="text-lg font-semibold">Customize</h2>
        <p className="text-sm text-muted-foreground mt-0.5">
          Adjust the appearance and behavior of your chatbot widget.
        </p>
      </div>

      <div className="grid lg:grid-cols-2 gap-6 items-start">
        {/* Form */}
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6" noValidate>
            <Card>
              <CardHeader>
                <CardTitle className="text-sm font-medium">Branding</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  {/* Primary color */}
                  <FormField
                    control={form.control}
                    name="primary_color"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Primary Color</FormLabel>
                        <FormControl>
                          <div className="relative">
                            <div
                              className="flex h-9 items-center gap-2 rounded-md border border-input px-3 cursor-pointer"
                              onClick={() => { setPrimaryPicker((p) => !p); setTextPicker(false); }}
                            >
                              <span
                                className="h-5 w-5 rounded-sm border"
                                style={{ backgroundColor: field.value }}
                              />
                              <span className="text-sm font-mono">{field.value}</span>
                            </div>
                            {primaryPicker && (
                              <div className="absolute z-10 mt-1">
                                <HexColorPicker color={field.value} onChange={field.onChange} />
                              </div>
                            )}
                          </div>
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  {/* Text color */}
                  <FormField
                    control={form.control}
                    name="text_color"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Text Color</FormLabel>
                        <FormControl>
                          <div className="relative">
                            <div
                              className="flex h-9 items-center gap-2 rounded-md border border-input px-3 cursor-pointer"
                              onClick={() => { setTextPicker((p) => !p); setPrimaryPicker(false); }}
                            >
                              <span
                                className="h-5 w-5 rounded-sm border"
                                style={{ backgroundColor: field.value }}
                              />
                              <span className="text-sm font-mono">{field.value}</span>
                            </div>
                            {textPicker && (
                              <div className="absolute z-10 mt-1">
                                <HexColorPicker color={field.value} onChange={field.onChange} />
                              </div>
                            )}
                          </div>
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                </div>

                {/* Font family */}
                <FormField
                  control={form.control}
                  name="font_family"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Font Family</FormLabel>
                      <FormControl>
                        <select
                          className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                          {...field}
                        >
                          {FONT_OPTIONS.map((f) => (
                            <option key={f} value={f}>{f}</option>
                          ))}
                        </select>
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="text-sm font-medium">Messages</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <FormField
                  control={form.control}
                  name="welcome_message"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Welcome Message</FormLabel>
                      <FormControl>
                        <Input placeholder="Hi! How can I help you today?" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="placeholder_text"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Input Placeholder</FormLabel>
                      <FormControl>
                        <Input
                          placeholder="Ask me anything..."
                          {...field}
                          value={field.value ?? ''}
                          onChange={(e) => field.onChange(e.target.value || null)}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="text-sm font-medium">Behavior</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <FormField
                    control={form.control}
                    name="ai_tone"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>AI Tone</FormLabel>
                        <FormControl>
                          <select
                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm capitalize focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            {...field}
                          >
                            {TONE_OPTIONS.map((t) => (
                              <option key={t} value={t} className="capitalize">{t}</option>
                            ))}
                          </select>
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name="position"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Widget Position</FormLabel>
                        <FormControl>
                          <select
                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            {...field}
                          >
                            {POSITION_OPTIONS.map((p) => (
                              <option key={p} value={p}>{p}</option>
                            ))}
                          </select>
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                </div>

                <FormField
                  control={form.control}
                  name="theme"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Widget Theme</FormLabel>
                      <FormControl>
                        <div className="flex gap-2">
                          {THEME_OPTIONS.map((t) => (
                            <button
                              key={t}
                              type="button"
                              onClick={() => field.onChange(t)}
                              className={`flex-1 rounded-md border py-1.5 text-sm capitalize transition-colors ${
                                field.value === t
                                  ? 'border-primary bg-primary/10 text-primary font-medium'
                                  : 'border-input text-muted-foreground hover:bg-muted'
                              }`}
                            >
                              {t}
                            </button>
                          ))}
                        </div>
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="ai_persona"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>AI Persona (optional)</FormLabel>
                      <FormControl>
                        <Input
                          placeholder="You are a helpful support agent for Acme Inc…"
                          {...field}
                          value={field.value ?? ''}
                          onChange={(e) => field.onChange(e.target.value || null)}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="show_branding"
                  render={({ field }) => (
                    <FormItem className="flex items-center gap-3">
                      <FormControl>
                        <input
                          type="checkbox"
                          className="h-4 w-4 rounded border-input"
                          checked={field.value}
                          onChange={(e) => field.onChange(e.target.checked)}
                        />
                      </FormControl>
                      <FormLabel className="!mt-0 cursor-pointer">Show &ldquo;Powered by ReplyIQ&rdquo; branding</FormLabel>
                    </FormItem>
                  )}
                />
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

        {/* Live Preview */}
        <div className="sticky top-6">
          <Card>
            <CardHeader>
              <CardTitle className="text-sm font-medium">Live Preview</CardTitle>
              <CardDescription className="text-xs">Static preview — interactions not available</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="relative bg-muted/30 rounded-lg h-96 overflow-hidden border">
                {/* Mock chat widget */}
                <div
                  className={`absolute ${
                    watchedValues.position?.includes('bottom') ? 'bottom-4' : 'top-4'
                  } ${
                    watchedValues.position?.includes('right') ? 'right-4' : 'left-4'
                  } w-72 shadow-xl rounded-2xl overflow-hidden border`}
                  style={{ fontFamily: watchedValues.font_family }}
                >
                  {/* Header */}
                  <div
                    className="px-4 py-3 flex items-center gap-2"
                    style={{ backgroundColor: watchedValues.primary_color }}
                  >
                    <div className="h-7 w-7 rounded-full bg-white/20 flex items-center justify-center shrink-0">
                      <Bot className="h-4 w-4 text-white" />
                    </div>
                    <span className="text-sm font-medium text-white">{chatbot?.name ?? 'Chatbot'}</span>
                  </div>

                  {/* Messages */}
                  <div className="bg-white dark:bg-zinc-900 px-3 py-3 space-y-2 min-h-[120px]">
                    <div className="flex gap-2">
                      <div className="h-6 w-6 rounded-full shrink-0 flex items-center justify-center" style={{ backgroundColor: watchedValues.primary_color + '22' }}>
                        <Bot className="h-3.5 w-3.5" style={{ color: watchedValues.primary_color }} />
                      </div>
                      <div
                        className="rounded-2xl rounded-tl-sm px-3 py-2 text-xs max-w-[85%]"
                        style={{ backgroundColor: watchedValues.primary_color + '15', color: watchedValues.text_color }}
                      >
                        {watchedValues.welcome_message || 'Hi! How can I help you today?'}
                      </div>
                    </div>
                  </div>

                  {/* Input */}
                  <div className="bg-white dark:bg-zinc-900 border-t px-3 py-2">
                    <div className="flex items-center gap-2 rounded-full border px-3 py-1.5">
                      <span className="text-xs text-muted-foreground flex-1 truncate">
                        {watchedValues.placeholder_text || 'Ask me anything...'}
                      </span>
                      <div
                        className="h-5 w-5 rounded-full flex items-center justify-center shrink-0"
                        style={{ backgroundColor: watchedValues.primary_color }}
                      >
                        <svg className="h-2.5 w-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                          <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                        </svg>
                      </div>
                    </div>
                    {watchedValues.show_branding && (
                      <p className="text-center text-[9px] text-muted-foreground mt-1">
                        Powered by ReplyIQ
                      </p>
                    )}
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
