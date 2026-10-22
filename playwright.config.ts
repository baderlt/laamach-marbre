import { defineConfig, devices } from '@playwright/test';

// Client-content checks are read-only. Synthetic admin mutations run last.
const clientContent = /(?:catalogue-final|client-projects|contact-refinement|dimension-studio|home-refinement|premium-catalogue|product-selection)\.spec\.ts/;
export default defineConfig({
  testDir: './tests/browser',
  workers: 1,
  retries: 0,
  reporter: 'list',
  use: { baseURL: process.env.QA_BASE_URL || 'http://127.0.0.1:3101', trace: 'retain-on-failure' },
  projects: [
    { name: 'chromium', testIgnore: [clientContent, /(?:viewer|admin-experience)\.spec\.ts/], use: { ...devices['Desktop Chrome'] } },
    // Multi-page functional checks on the local development preview, not timing budgets.
    { name: 'client-content', testMatch: clientContent, timeout: 120000, use: { ...devices['Desktop Chrome'], actionTimeout: 15000, baseURL: process.env.QA_CLIENT_BASE_URL || 'http://127.0.0.1:3105' } },
    { name: 'viewer', testMatch: /viewer\.spec\.ts/, dependencies: ['chromium', 'client-content'], use: { ...devices['Desktop Chrome'] } },
    { name: 'admin', testMatch: /admin-experience\.spec\.ts/, dependencies: ['viewer'], use: { ...devices['Desktop Chrome'] } },
  ],
});
