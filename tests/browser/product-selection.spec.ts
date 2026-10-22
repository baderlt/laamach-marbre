import { test, expect, type APIRequestContext, type Page } from '@playwright/test';
const api = process.env.QA_CLIENT_API_URL || 'http://127.0.0.1:8101';
test.setTimeout(120000);

async function catalogue(request: APIRequestContext, locale: string, url: string) {
  const query = new URLSearchParams([...new URL(url).searchParams].filter(([key, value]) => value !== '' && ['parent', 'category', 'finish', 'q', 'sort', 'page'].includes(key)));
  query.set('locale', locale);
  const grouped = [...query.keys()].every(key => key === 'locale' || key === 'page');
  const response = await request.get(`${api}/api/v1/${grouped ? 'product-groups' : 'products'}?${query}`);
  expect(response.status()).toBe(200);
  return response.json();
}
async function allProducts(page: Page, request: APIRequestContext, locale: string) {
  await page.waitForLoadState('load');
  const expected = await catalogue(request, locale, page.url());
  const cards = page.locator('.selection-card');
  await expect(page.locator('.results-count strong')).toHaveText(String(expected.meta.total));
  for (let attempt = 0; attempt < expected.meta.last_page && await cards.count() < expected.meta.total; attempt++) {
    const before = await cards.count();
    await page.locator('.catalogue-load-more').scrollIntoViewIfNeeded();
    await expect.poll(() => cards.count(), { timeout: 30000 }).toBeGreaterThan(before);
  }
  await expect(cards).toHaveCount(expected.meta.total);
  expect(new Set(await cards.locator('.card-copy a').evaluateAll(links => links.map(link => link.getAttribute('href')))).size).toBe(expected.meta.total);
  return expected.meta.total as number;
}
for (const locale of ['fr', 'ar']) {
  test(`${locale}: catalogue search, sorting, filters and display modes preserve the real selection`, async ({ page, request }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`/${locale}/products`);
    const total = await allProducts(page, request, locale);
    expect(total).toBeGreaterThan(0);
    await expect(page.locator('.pagination')).toHaveCount(0);
    await expect(page.locator('.catalogue-hero img')).toBeVisible();
    const categories = await (await request.get(`${api}/api/v1/product-categories?locale=${locale}`)).json();
    await expect(page.locator('.catalogue-filters select[name="finish"] option')).toHaveCount(categories.finishes.length ? categories.finishes.length + 1 : 0);
    await expect(page.locator('.selection-card .card-copy > p')).toHaveCount(0);
    const categoryLinks = page.locator('.catalogue-category-swiper a');
    expect(await categoryLinks.count()).toBeGreaterThan(1);
    await categoryLinks.nth(1).click();
    await expect(page).toHaveURL(/(?:parent|category)=/);
    await expect(categoryLinks.nth(1)).toHaveAttribute('aria-current', 'page');
    await allProducts(page, request, locale);
    await categoryLinks.first().click();
    await expect(page).toHaveURL(new RegExp(`/${locale}/products$`));
    await page.locator('.catalogue-view-switch button').nth(1).click();
    await expect(page.locator('.catalogue-results')).toHaveAttribute('data-layout', 'list');
    await expect(page.locator('.catalogue-view-switch button').nth(1)).toHaveAttribute('aria-pressed', 'true');
    await allProducts(page, request, locale);
    await page.locator('.catalogue-view-switch button').first().click();
    await expect(page.locator('.catalogue-results')).toHaveAttribute('data-layout', 'grid');
    const query = locale === 'fr' ? 'Noir' : 'أسود';
    await page.locator('.catalogue-search input[name="q"]').fill(query);
    await page.locator('.catalogue-search button').click();
    await expect(page).toHaveURL(/q=/);
    const found = await allProducts(page, request, locale);
    expect(found).toBeGreaterThan(0);
    await page.locator('.catalogue-sort select').selectOption('name');
    await expect(page).toHaveURL(/sort=name/);
    expect(new URL(page.url()).searchParams.get('q')).toBe(query);
    expect(await allProducts(page, request, locale)).toBe(found);
    await page.locator('.catalogue-filters select[name="parent"]').selectOption({ index: 1 });
    await page.locator('.catalogue-filters select[name="category"]').selectOption({ index: 1 });
    await page.locator('.catalogue-filters button[type="submit"]').click();
    await expect(page).toHaveURL(/category=/);
    expect(new URL(page.url()).searchParams.get('q')).toBe(query);
    expect(new URL(page.url()).searchParams.get('sort')).toBe('name');
    await allProducts(page, request, locale);
    await page.locator('.catalogue-filters .filter-reset').click();
    await allProducts(page, request, locale);
    await page.locator('.catalogue-search input[name="q"]').fill('zzzz-unmatched-material');
    await page.locator('.catalogue-search button').click();
    await expect(page.locator('.editorial-empty')).toBeVisible();
    await expect(page.locator('.selection-card')).toHaveCount(0);
    await page.locator('.editorial-empty a').click();
    await allProducts(page, request, locale);
    for (const width of [320, 390, 768, 1024, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('.catalogue-sort')).not.toBeVisible();
    await expect(page.locator('.category-swipe-controls')).not.toBeVisible();
    const search = (await page.locator('.catalogue-search').boundingBox())!;
    const views = (await page.locator('.catalogue-view-switch').boundingBox())!;
    expect(Math.abs(search.y + search.height / 2 - views.y - views.height / 2)).toBeLessThan(2);
    const trigger = page.locator('.mobile-filter-toggle');
    await trigger.click(); await expect(page.locator('.filter-dialog')).toBeVisible();
    await page.keyboard.press('Escape'); await expect(page.locator('.filter-dialog')).not.toBeVisible();
    await expect(trigger).toBeFocused();
    await page.locator('.selection-card .card-copy a').first().click();
    await expect(page).toHaveURL(new RegExp(`/${locale}/products/[^?]+$`));
    await expect(page.locator('.product-detail h1')).toBeVisible();
    expect(errors).toEqual([]);
  });
}

test('catalogue remains searchable and filterable without JavaScript', async ({ browser, request }) => {
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  try {
    await page.goto('/fr/products');
    const initial = await catalogue(request, 'fr', page.url());
    await expect(page.locator('.selection-card')).toHaveCount(initial.data.length);
    await page.locator('.catalogue-category-swiper a').nth(1).click();
    await expect(page).toHaveURL(/(?:parent|category)=/);
    await page.locator('.catalogue-search input[name="q"]').fill('Noir');
    await page.locator('.catalogue-search button').click();
    let expected = await catalogue(request, 'fr', page.url());
    await expect(page.locator('.selection-card')).toHaveCount(expected.data.length);
    await page.locator('.catalogue-filters select[name="parent"]').selectOption({ index: 1 });
    await page.locator('.catalogue-filters button[type="submit"]').click();
    expected = await catalogue(request, 'fr', page.url());
    await expect(page.locator('.selection-card')).toHaveCount(expected.data.length);
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.locator('.catalogue-sort select').selectOption('name');
    await page.locator('.catalogue-sort button[type="submit"]').click();
    await expect(page).toHaveURL(/sort=name/);
    expected = await catalogue(request, 'fr', page.url());
    await expect(page.locator('.selection-card')).toHaveCount(expected.data.length);
  } finally { await context.close(); }
});
for (const locale of ['fr', 'ar']) {
  test(`${locale}: project photo banner and explicit gallery links remain usable on mobile`, async ({ page, request }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.setViewportSize({ width: 618, height: 668 });
    await page.goto(`/${locale}/projects`);
    const expected = (await (await request.get(`${api}/api/v1/projects?locale=${locale}`)).json()).data.length;
    const banner = page.locator('.portfolio-hero');
    const photo = (await banner.locator('.hero-media').boundingBox())!, heading = (await banner.locator('h1').boundingBox())!;
    expect(heading.y).toBeGreaterThan(photo.y);
    expect(heading.y + heading.height).toBeLessThan(photo.y + photo.height);
    await expect(banner.locator('h1')).toHaveCSS('color', 'rgb(255, 255, 255)');
    await expect(page.locator('.announcement-delivery')).toBeVisible();
    await expect(page.locator('.project-gallery-link')).toHaveCount(expected);
    for (const width of [320, 390, 618, 768, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    const link = page.locator('.project-gallery-link').first(), href = await link.getAttribute('href');
    await link.click(); await expect(page).toHaveURL(new RegExp(`${href}$`));
    await expect(page.locator('.project-gallery')).toBeVisible();
  });
}
