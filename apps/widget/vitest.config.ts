import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'jsdom',
    globals:     true,
    setupFiles:  ['./src/loader/test-setup.ts'],
    include:     ['src/**/*.test.ts', 'src/**/*.test.tsx'],
  },
  define: {
    __WIDGET_BASE__:    '"https://widget.replyiq.com"',
    __API_BASE__:       '"https://api.replyiq.com"',
    __WIDGET_API_URL__: '"https://api.replyiq.com/api/v1"',
    __REVERB_APP_KEY__: '"test-key"',
    __REVERB_HOST__:    '"localhost"',
    __REVERB_PORT__:    6001,
    __REVERB_SCHEME__:  '"ws"',
    __BUILD_HASH__:     '"test"',
  },
});
