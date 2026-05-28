import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

/**
 * Iframe app bundle: dist/app/{index.html,assets/…}
 *
 * Full React 18 app that runs inside the sandboxed iframe.
 * Fetches chatbot config from the public API and renders the chat UI.
 *
 * Constraints:
 *  - ES2020 target (we control the iframe's HTML — no legacy browser concern).
 *  - All assets output under dist/app/ so the CDN can serve the full directory.
 *  - React, laravel-echo, pusher-js all bundled (no external CDN links inside
 *    the iframe — strict CSP would block them).
 */
export default defineConfig({
  plugins: [react()],
  root:      '.',           // index.html is at apps/widget/index.html
  publicDir: false,         // public/widget.js is a separate build output, not a static asset
  build: {
    outDir:      'dist/app',
    emptyOutDir:  true,
    target:      'es2020',
    minify:      'terser',
    terserOptions: {
      compress: {
        passes:       2,
        drop_console: true,           // strip console.* from production app
      },
      format: { comments: false },
    },
    cssCodeSplit: true,               // extract CSS into separate file(s)
    rollupOptions: {
      input: { main: 'index.html' },
      output: {
        assetFileNames: 'assets/[name]-[hash][extname]',
        chunkFileNames: 'assets/[name]-[hash].js',
        entryFileNames: 'assets/[name]-[hash].js',
      },
    },
  },
  define: {
    __WIDGET_API_URL__: JSON.stringify(
      process.env['WIDGET_API_URL'] ?? 'https://api.replyiq.com/api/v1',
    ),
    __REVERB_APP_KEY__: JSON.stringify(
      process.env['REVERB_APP_KEY'] ?? 'replyiq',
    ),
    __REVERB_HOST__: JSON.stringify(
      process.env['REVERB_HOST'] ?? 'ws.replyiq.com',
    ),
    __REVERB_PORT__: Number(process.env['REVERB_PORT'] ?? 443),
    __REVERB_SCHEME__: JSON.stringify(
      process.env['REVERB_SCHEME'] ?? 'wss',
    ),
  },
});
