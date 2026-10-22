import { test, expect } from '@playwright/test';
const api = process.env.QA_CLIENT_API_URL || 'http://127.0.0.1:8101';
test.setTimeout(120000);

test('header search waits for typing, supports keyboard navigation and rejects invalid queries', async ({ page, request }) => {
  for (const query of ['locale=xx&q=noir', 'locale=fr&q=n', 'locale=fr&q=', `locale=fr&q=${'x'.repeat(121)}`]) {
    expect((await request.get(`/api/search?${query}`)).status()).toBe(400);
  }
  const calls: string[] = [];
  page.on('request', req => { if (new URL(req.url()).pathname === '/api/search') calls.push(req.url()); });
  await page.goto('/fr');
  const trigger = page.locator('.header-search');
  await trigger.click();
  const input = page.locator('.search-dialog').getByRole('combobox');
  await expect(input).toBeFocused();
  await input.fill('N');
  await page.waitForTimeout(500);
  expect(calls).toHaveLength(0);
  await input.fill('No');
  await page.waitForTimeout(100);
  await input.fill('Noi');
  await page.waitForTimeout(100);
  await input.fill('Noir');
  await page.waitForTimeout(200);
  expect(calls).toHaveLength(0);
  await expect(page.locator('.search-suggestions a').first()).toBeVisible();
  expect(calls).toHaveLength(1);
  expect(new URL(calls[0]).searchParams.get('q')).toBe('Noir');
  expect(await page.locator('.search-suggestions a').count()).toBeLessThanOrEqual(6);
  await expect(page.locator('.search-suggestions img').first()).toBeVisible();
  const firstHref = await page.locator('.search-suggestions a').first().getAttribute('href');
  await input.press('ArrowDown');
  await expect(page.getByRole('option').first()).toHaveAttribute('aria-selected', 'true');
  await input.press('Enter');
  await expect(page).toHaveURL(new RegExp(`${firstHref}$`));
  // URL commit precedes the new document's scripts; wait before reopening its dialog.
  await page.waitForLoadState('load');
  await trigger.click();
  await expect(page.locator('.search-dialog')).toBeVisible();
  await input.fill('Noir');
  await input.press('Escape');
  await expect(trigger).toBeFocused();
  const count = calls.length;
  await page.waitForTimeout(500);
  expect(calls).toHaveLength(count);
});

test('search ignores stale replies, IME composition and reports failure accessibly', async ({ page, request }) => {
  const actual = await (await request.get('/api/search?locale=fr&q=Noir')).json();
  let started = false;
  await page.route('**/api/search?**', async route => {
    const q = new URL(route.request().url()).searchParams.get('q');
    if (q === 'Noir') { started = true; await new Promise(resolve => setTimeout(resolve, 900)); }
    if (q === 'Erreur') await route.fulfill({ status: 503, json: { data: [], total: 0 } });
    else await route.fulfill({ json: q === 'Noir' ? actual : { data: [], total: 0 } });
  });
  await page.goto('/fr/products');
  await page.locator('.header-search').click();
  const input = page.locator('.search-dialog').getByRole('combobox');
  await input.dispatchEvent('compositionstart');
  await input.fill('Noir');
  await page.waitForTimeout(500);
  expect(started).toBe(false);
  await input.dispatchEvent('compositionend');
  await expect.poll(() => started).toBe(true);
  await input.fill('Aucun');
  await expect(page.locator('.search-status')).toHaveText('0 résultats');
  await page.waitForTimeout(1000);
  await expect(page.locator('.search-dialog').getByRole('option')).toHaveCount(0);
  await input.fill('Erreur');
  await expect(page.locator('.search-status')).toContainText('indisponible');
  await input.press('Escape');
});

for (const locale of ['fr', 'ar']) {
  test(`${locale}: instant category selection, premium product and collection gallery`, async ({ page, request }) => {
    const total = (await (await request.get(`${api}/api/v1/projects?locale=${locale}`)).json()).meta.total;
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto(`/${locale}/projects`);
    await expect(page.locator('.project-card')).toHaveCount(total);
    await expect(page.locator('.pagination')).toHaveCount(0);
    const filters = page.locator('.project-category-options button');
    await filters.nth(1).click();
    await expect(filters.nth(1)).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('.project-card')).toHaveCount(1);
    await filters.nth(2).click();
    await expect(filters.nth(2)).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('.project-card')).toHaveCount(2);
    expect(new URL(page.url()).searchParams.getAll('categories[]')).toHaveLength(2);
    await page.goBack();
    await expect(page.locator('.project-card')).toHaveCount(1);
    await filters.first().click();
    await expect(page.locator('.project-card')).toHaveCount(total);

    await page.goto(`/${locale}/projects/pierre-pose-traditionnelle`);
    await expect(page.locator('.project-metadata')).toHaveCount(0);
    await expect(page.locator('.project-gallery-choices button').first()).toHaveAccessibleName(locale === 'ar' ? 'الصورة الرئيسية' : 'Vue principale');
    await expect(page.locator('.project-gallery-choices button span')).toHaveCount(0);
    const gallery = await page.locator('.project-gallery').boundingBox();
    const enquiry = await page.locator('.project-enquiry').boundingBox();
    expect(enquiry!.y).toBeGreaterThan(gallery!.y + gallery!.height);
    await page.locator('.project-gallery-choices button').nth(2).click();
    await expect(page.locator('.project-gallery-choices button').nth(2)).toHaveAttribute('aria-pressed', 'true');
    await page.locator('.gallery-expand').click();
    await expect(page.locator('.project-lightbox')).toBeVisible();
    await page.keyboard.press('Escape');

    await page.goto(`/${locale}/products/noir-khnifra`);
    await expect(page.locator('main h1')).toHaveCount(1);
    await expect(page.locator('.material-benefits li')).toHaveCount(4);
    await expect(page.locator('.facts-table > div')).toHaveCount(7);
    const whatsapp = page.locator('.floating-whatsapp');
    await expect(whatsapp).toHaveCSS('position', 'fixed');
    expect(new URL((await whatsapp.getAttribute('href'))!).pathname).toBe('/212706795408');
    await page.locator('.gallery-zoom').click();
    await expect(page.locator('.image-lightbox')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('.gallery-zoom')).toBeFocused();
    for (const width of [320, 360, 390, 658, 784, 1242, 1280, 1366, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await expect(page.locator('.header-search')).toBeVisible();
      if (width < 768) await expect(page.locator('.mobile-contact-bar')).toBeVisible();
      else await expect(whatsapp).toBeVisible();
    }
    await expect(page.locator('meta[property="og:image"]')).toHaveCount(1);
    expect(errors).toEqual([]);
  });
}
