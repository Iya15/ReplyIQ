import { defineConfig } from 'vite';

/**
 * Loader bundle: public/widget.js
 *
 * Tiny IIFE that runs directly on the host page. Reads window.riq,
 * validates the init() call, and injects the widget iframe.
 *
 * Constraints:
 *  - No React, no external dependencies — plain DOM APIs only.
 *  - IIFE so it self-executes after the host page's <script> tag.
 *  - ES2018 target for broadest embed-site compatibility.
 *  - __WIDGET_BASE__ is baked in at build time via the WIDGET_BASE env var.
 */
export default defineConfig(({ mode }) => ({
  publicDir: false, // don't copy public/ into outDir (we ARE outDir)
  define: {
    __WIDGET_BASE__: JSON.stringify(
      process.env['WIDGET_BASE'] ?? 'https://cdn.replyiq.com/widget',
    ),
    __API_BASE__: JSON.stringify(
      process.env['WIDGET_API_BASE'] ?? 'https://api.replyiq.com',
    ),
  },
  build: {
    lib: {
      entry:    'src/loader/index.ts',
      name:     'ReplyIQLoader',
      formats:  ['iife'],
      fileName: () => 'widget.js',     // → public/widget.js
    },
    outDir:      'public',
    emptyOutDir: false,                // index.html lives here too
    target:     'es2018',
    minify:     mode === 'production' ? 'terser' : false,
    terserOptions: {
      compress: {
        passes:       2,
        drop_console: false,           // keep console.log in skeleton
        pure_getters: true,
        unsafe_math:  true,
      },
      mangle: { toplevel: true },
      format: { comments: false },
    },
    sourcemap: false,
    rollupOptions: {
      external: [],                    // bundle everything — no externals
    },
  },
}));
