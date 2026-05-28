import { test, expect, type Page } from '@playwright/test';

// ── Mock helpers ──────────────────────────────────────────────────────────────

const CHATBOT_ID  = 'test-bot-001';
const CONV_ID     = 'conv-e2e-001';
const SESSION_TOK = 'tok_e2e_test';

const MOCK_CONFIG = {
  name:            'Test Bot',
  primary_color:   '#4F46E5',
  text_color:      '#0F172A',
  font_family:     'Inter',
  theme:           'light',
  position:        'bottom-right',
  welcome_message: 'Hello! How can I help you today?',
  placeholder_text:'Ask me anything…',
  show_branding:   false,
};

const MOCK_USER_MSG = {
  id: 'msg-user-001', role: 'user', content: 'Hello from E2E',
  status: 'complete', sources: [], confidence: null,
  created_at: new Date().toISOString(),
};

const MOCK_ASST_MSG = {
  id: 'msg-asst-001', role: 'assistant', content: 'Hi! I am the test assistant.',
  status: 'complete', sources: [], confidence: null,
  created_at: new Date().toISOString(),
};

async function setupApiMocks(page: Page) {
  // Config endpoint
  await page.route(`**/api/v1/public/chatbots/${CHATBOT_ID}/config`, (route) => {
    route.fulfill({ json: { data: MOCK_CONFIG } });
  });

  // Start conversation
  await page.route('**/api/v1/public/conversations', (route) => {
    route.fulfill({
      json: {
        session_token: SESSION_TOK,
        data: { id: CONV_ID },
      },
    });
  });

  // Load messages (empty initially)
  await page.route(`**/api/v1/public/conversations/${CONV_ID}/messages`, (route) => {
    if (route.request().method() === 'GET') {
      route.fulfill({ json: { data: [] } });
    } else {
      // POST — send message
      route.fulfill({
        json: {
          data: {
            user_message:      MOCK_USER_MSG,
            assistant_message: { ...MOCK_ASST_MSG, content: '', status: 'pending' },
          },
        },
      });
    }
  });

  // Broadcasting auth (presence channel)
  await page.route('**/api/v1/public/broadcasting/auth', (route) => {
    route.fulfill({ json: { auth: 'test:sig', channel_data: '{}' } });
  });
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test.describe('Widget launcher', () => {
  test.beforeEach(async ({ page }) => {
    await setupApiMocks(page);
    await page.goto('/e2e/test-page.html');
  });

  test('launcher button appears after config loads', async ({ page }) => {
    const launcher = page.locator('#riq-launcher');
    await expect(launcher).toBeVisible({ timeout: 5000 });
  });

  test('clicking launcher opens the iframe', async ({ page }) => {
    await page.locator('#riq-launcher').click();
    const iframe = page.locator('#riq-widget');
    await expect(iframe).toBeVisible({ timeout: 3000 });
  });

  test('iframe has sandbox attribute', async ({ page }) => {
    await page.locator('#riq-launcher').click();
    const sandbox = await page.locator('#riq-widget').getAttribute('sandbox');
    expect(sandbox).toContain('allow-scripts');
    expect(sandbox).toContain('allow-same-origin');
  });
});

test.describe('Chat conversation', () => {
  test.beforeEach(async ({ page }) => {
    await setupApiMocks(page);
    await page.goto('/e2e/test-page.html');
    // Open widget
    await page.locator('#riq-launcher').click();
    await expect(page.locator('#riq-widget')).toBeVisible({ timeout: 3000 });
  });

  test('welcome message is shown in the iframe', async ({ page }) => {
    const frame = page.frameLocator('#riq-widget');
    await expect(frame.getByText(MOCK_CONFIG.welcome_message)).toBeVisible({ timeout: 5000 });
  });

  test('user can type and send a message', async ({ page }) => {
    const frame = page.frameLocator('#riq-widget');
    const input = frame.getByLabel('Message input');
    await input.fill('Hello from E2E');
    await input.press('Enter');
    await expect(frame.getByText('Hello from E2E')).toBeVisible({ timeout: 5000 });
  });

  test('close button hides the iframe', async ({ page }) => {
    const frame   = page.frameLocator('#riq-widget');
    const closeBtn = frame.getByLabel(/close/i);
    await expect(closeBtn).toBeVisible({ timeout: 5000 });
    await closeBtn.click();
    const iframe = page.locator('#riq-widget');
    await expect(iframe).not.toBeVisible({ timeout: 2000 });
  });
});
