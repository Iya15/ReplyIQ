import type { Config } from 'tailwindcss';
import preset from '@replyiq/config/tailwind-preset';

/**
 * Widget-specific Tailwind config.
 *
 * Uses the shared @replyiq/config preset for brand tokens (colors, fonts,
 * shadows) while keeping content scanning scoped to the widget source only —
 * it does NOT share with the web dashboard.
 *
 * The widget runs inside an iframe so Tailwind's preflight (CSS reset) is
 * safe to include — it won't bleed into the host page.
 */
const config: Config = {
  presets: [preset],
  content: [
    './src/iframe-app/**/*.{ts,tsx}',
    './index.html',
  ],
  theme: {
    extend: {
      colors: {
        // Override at runtime from chatbot branding via CSS custom property.
        primary: 'var(--color-primary, #4F46E5)',
      },
    },
  },
};

export default config;
