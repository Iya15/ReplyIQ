'use client';

import { useState, useCallback } from 'react';
import Link from 'next/link';
import { CheckCircle, Circle, X, Zap } from 'lucide-react';

interface Step {
  id:    string;
  label: string;
  hint:  string;
  href?: string;
}

const STEPS: Step[] = [
  {
    id:    'created',
    label: 'Create your account',
    hint:  "You're in!",
  },
  {
    id:    'knowledge',
    label: 'Add knowledge to your chatbot',
    hint:  'Upload a document, paste text, or crawl your website.',
    href:  '/chatbots',
  },
  {
    id:    'customize',
    label: 'Customize the widget',
    hint:  'Set your brand color, logo, and welcome message.',
    href:  '/chatbots',
  },
  {
    id:    'embed',
    label: 'Embed on your website',
    hint:  'Copy the script tag and paste it into your site.',
    href:  '/chatbots',
  },
];

const STORAGE_KEY = 'riq:onboarding:dismissed';

function isDismissed(): boolean {
  try { return localStorage.getItem(STORAGE_KEY) === '1'; } catch { return false; }
}

export function OnboardingChecklist() {
  const [dismissed, setDismissed] = useState(() => isDismissed());
  const [completed, setCompleted] = useState<Set<string>>(
    () => new Set(['created']), // 'created' is always done
  );

  const toggle = useCallback((id: string) => {
    setCompleted((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  }, []);

  const dismiss = useCallback(() => {
    try { localStorage.setItem(STORAGE_KEY, '1'); } catch {}
    setDismissed(true);
  }, []);

  const doneCount = completed.size;
  const total     = STEPS.length;
  const allDone   = doneCount === total;

  if (dismissed || allDone) return null;

  return (
    <div
      className="rounded-xl border bg-card p-5 space-y-4"
      aria-label="Getting started checklist"
      role="region"
    >
      {/* Header */}
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
            <Zap className="h-4 w-4 text-primary" aria-hidden />
          </div>
          <div>
            <p className="text-sm font-semibold">Get started</p>
            <p className="text-xs text-muted-foreground">{doneCount} of {total} steps done</p>
          </div>
        </div>
        <button
          onClick={dismiss}
          className="text-muted-foreground hover:text-foreground transition-colors mt-0.5"
          aria-label="Dismiss checklist"
        >
          <X className="h-4 w-4" />
        </button>
      </div>

      {/* Progress bar */}
      <div className="h-1.5 rounded-full bg-muted overflow-hidden">
        <div
          className="h-full bg-primary rounded-full transition-all"
          style={{ width: `${Math.round((doneCount / total) * 100)}%` }}
          role="progressbar"
          aria-valuenow={doneCount}
          aria-valuemin={0}
          aria-valuemax={total}
        />
      </div>

      {/* Steps */}
      <ul className="space-y-2.5">
        {STEPS.map(({ id, label, hint, href }) => {
          const done = completed.has(id);
          return (
            <li key={id} className="flex items-start gap-3">
              <button
                onClick={() => toggle(id)}
                disabled={id === 'created'}
                className="mt-0.5 shrink-0 disabled:cursor-default"
                aria-label={done ? `Mark "${label}" as incomplete` : `Mark "${label}" as complete`}
              >
                {done ? (
                  <CheckCircle className="h-5 w-5 text-green-500" aria-hidden />
                ) : (
                  <Circle className="h-5 w-5 text-muted-foreground" aria-hidden />
                )}
              </button>
              <div className={`flex-1 ${done ? 'opacity-50' : ''}`}>
                <div className="flex items-center gap-2 flex-wrap">
                  <span className={`text-sm font-medium ${done ? 'line-through text-muted-foreground' : ''}`}>
                    {label}
                  </span>
                  {href && !done && (
                    <Link
                      href={href}
                      className="text-[10px] font-medium text-primary hover:underline"
                    >
                      Go →
                    </Link>
                  )}
                </div>
                {!done && <p className="text-xs text-muted-foreground mt-0.5">{hint}</p>}
              </div>
            </li>
          );
        })}
      </ul>
    </div>
  );
}
