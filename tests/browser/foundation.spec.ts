import { test, expect } from '@playwright/test';
import locales from '../../config/locales.json';

const api = process.env.QA_API_URL || 'http://127.0.0.1:8101';

test('root redirects to the configured default locale', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveURL(new RegExp(`/${locales.default}$`));
});

for (const locale of locales.locales) {
  test(`${locale.code}: shell, direction, connectivity and language links`, async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    const response = await page.goto(`/${locale.code}`);
    expect(response?.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', locale.code);
    await expect(page.locator('html')).toHaveAttribute('dir', locale.direction);
    await expect(page.locator('[data-backend-status]')).toHaveAttribute('data-backend-status', 'ok');
    await expect(page.getByRole('navigation').getByRole('link')).toHaveCount(locales.locales.length);
    for (const width of [390, 1280]) {
      await page.setViewportSize({ width, height: 844 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    }
    expect(errors).toEqual([]);
  });
}

test('disabled locales and unknown paths return 404', async ({ page }) => {
  expect((await page.goto('/en'))?.status()).toBe(404);
  expect((await page.goto('/fr/not-a-page'))?.status()).toBe(404);
});

test('versioned health and Filament login are available', async ({ request, page }) => {
  const health = await request.get(`${api}/api/v1/health`);
  expect(health.status()).toBe(200);
  expect(await health.json()).toMatchObject({ application: 'laamach-marbre', status: 'ok' });
  expect((await page.goto(`${api}/admin/login`))?.status()).toBe(200);
  await expect(page.locator('input[type="email"]')).toBeVisible();
  await expect(page.locator('input[type="password"]')).toBeVisible();
  expect((await request.get(`${api}/admin/register`)).status()).toBe(404);
});

test('locale metadata cannot be overridden by a client header', async ({ request }) => {
  const response = await request.get('/fr', { headers: { 'x-laamach-locale': 'ar' } });
  expect(await response.text()).toMatch(/<html[^>]*lang="fr"[^>]*dir="ltr"/);
});

test('web form-token validation stays enabled and no shared SPA cookie is issued', async ({ request }) => {
  const response = await request.get(`${api}/admin/login`);
  expect(response.headers()['set-cookie'] || '').not.toContain('XSRF-TOKEN=');
  // An unauthenticated admin POST redirects before CSRF validation. The root
  // web route exercises token validation without an authentication redirect.
  const rejected = await request.post(`${api}/`, { data: {} });
  expect(rejected.status()).toBe(419);
});

test('M2 media boundaries reject anonymous admin access and arbitrary object paths', async ({ request }) => {
  const id = '00000000-0000-4000-8000-000000000001';
  const media = await request.get(`${api}/admin/media-assets`);
  expect(media.url()).toContain('/admin/login');
  const preview = await request.get(`${api}/admin/media-preview/${id}`, { headers: { Accept: 'application/json' } });
  expect(preview.status()).toBe(401);
  expect((await request.get(`${api}/media/originals/${id}/original.png`)).status()).toBe(404);
  expect((await request.get(`${api}/storage/originals/${id}/original.png`)).status()).toBe(404);
  expect((await request.get(`${api}/media/images/${id}/thumb-v1/${'a'.repeat(64)}.webp`)).status()).toBe(404);
});
