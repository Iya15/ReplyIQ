/**
 * ReplyIQ Loader — public/widget.js
 *
 * Tiny IIFE bundled for ES2018. Runs on the host page after the embed snippet:
 *
 *   <script>
 *     window.riq = window.riq || [];
 *     window.riq.push(['init', { chatbotId: 'abc123' }]);
 *   </script>
 *   <script async src="https://cdn.replyiq.com/widget.js"></script>
 *
 * This file:
 *   1. Drains any commands queued before the script loaded.
 *   2. Replaces window.riq with a live command handler.
 *   3. On 'init', validates options and builds the iframe URL.
 *      (Actual iframe injection comes in M3.4.)
 *
 * No React, no imports — plain DOM APIs only.
 */

// ── Types ──────────────────────────────────────────────────────────────────────

type RiqCommand = readonly [string] | readonly [string, unknown];

interface RiqInitOptions {
  chatbotId: string;
}

// Extend the global Window type without using a module-scope declaration.
// (tsconfig moduleDetection: force makes this file a module, so declare global works.)
declare global {
  interface Window {
    riq?: RiqCommand[] | { push: (cmd: RiqCommand) => void };
  }
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

function isValidInitOptions(value: unknown): value is RiqInitOptions {
  return (
    isRecord(value) &&
    typeof value['chatbotId'] === 'string' &&
    (value['chatbotId'] as string).length > 0
  );
}

// ── Command processor ──────────────────────────────────────────────────────────

function processCommand(command: string, options?: unknown): void {
  if (command === 'init') {
    if (!isValidInitOptions(options)) {
      console.warn('[ReplyIQ] init() requires { chatbotId: string }');
      return;
    }
    const iframeUrl = `${__WIDGET_BASE__}/${options.chatbotId}`;
    // M3.4 will replace this log with the actual iframe injection.
    console.log('[ReplyIQ] init — chatbotId:', options.chatbotId, '| iframe URL:', iframeUrl);
  } else {
    console.warn('[ReplyIQ] unknown command:', command);
  }
}

// ── Boot ───────────────────────────────────────────────────────────────────────

// Drain commands queued before this script loaded.
const prior = window.riq;
if (Array.isArray(prior)) {
  for (const item of prior) {
    if (Array.isArray(item) && typeof item[0] === 'string') {
      processCommand(item[0], item[1]);
    }
  }
}

// Replace window.riq with a live handler for commands queued after load.
window.riq = {
  push(cmd: RiqCommand): void {
    const command = cmd[0];
    const options = cmd.length > 1 ? cmd[1] : undefined;
    processCommand(command, options);
  },
};
