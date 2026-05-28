import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env['CI'],
  retries: process.env['CI'] ? 2 : 0,
  workers: process.env['CI'] ? 1 : 4,
  reporter: 'html',
  use: {
    baseURL: 'http://localhost:4174',
    trace: 'on-first-retry',
  },

  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'firefox',  use: { ...devices['Desktop Firefox'] } },
    { name: 'webkit',   use: { ...devices['Desktop Safari'] } },
  ],

  // Serve the built widget files. Run `pnpm build` first (CI does this).
  // The test page is at /e2e/test-page.html, served from the project root.
  webServer: {
    command: 'pnpm exec vite preview --config vite.config.app.ts --port 4174',
    url: 'http://localhost:4174',
    reuseExistingServer: !process.env['CI'],
    timeout: 30_000,
  },
});
