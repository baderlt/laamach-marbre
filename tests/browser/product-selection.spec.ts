import { test, expect } from '@playwright/test';

test.setTimeout(60_000);

for (const locale of ['fr', 'ar']) {
  test(`${locale}: catalogue search, sorting, filters and display modes preserve the real selection`, async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`/${locale}/products`);
    await expect(page.locator('.selection-card')).toHaveCount(7);
    await expect(page.locator('.results-count')).toContainText('7');
    await expect(page.locator('.pagination')).toHaveCount(0);
    await expect(page.locator('.catalogue-hero img')).toBeVisible();
    expect(await page.locator('.catalogue-hero img').evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true);
    await expect(page.locator('.catalogue-filters select[name="finish"]')).toHaveCount(0);
    await expect(page.locator('.selection-card .card-copy > p')).toHaveCount(0);
    const categoryLinks = page.locator('.catalogue-category-swiper a');
    await expect(categoryLinks).toHaveCount(2);
    await categoryLinks.nth(1).click();
    await expect(page).toHaveURL(/category=/);
    await expect(categoryLinks.nth(1)).toHaveAttribute('aria-current', 'page');
    await expect(page.locator('.selection-card')).toHaveCount(7);
    await categoryLinks.first().click();
    await expect(page).toHaveURL(new RegExp(`/${locale}/products$`));
    await expect(categoryLinks.first()).toHaveAttribute('aria-current', 'page');
    await page.locator('.catalogue-view-switch button').nth(1).click();
    await expect(page.locator('.catalogue-results')).toHaveAttribute('data-layout', 'list');
    await expect(page.locator('.catalogue-view-switch button').nth(1)).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('.selection-card')).toHaveCount(7);
    await page.locator('.catalogue-view-switch button').first().click();
    await expect(page.locator('.catalogue-results')).toHaveAttribute('data-layout', 'grid');

    const q = locale === 'fr' ? 'Noir' : 'أسود';
    await page.locator('.catalogue-search input[name="q"]').fill(q);
    await page.locator('.catalogue-search button').click();
    await expect(page).toHaveURL(new RegExp('q='));
    await expect(page.locator('.selection-card')).toHaveCount(3);
    await page.waitForLoadState('networkidle');
    await page.locator('.catalogue-sort select').selectOption('name');
    await expect(page).toHaveURL(/sort=name/);
    expect(new URL(page.url()).searchParams.get('q')).toBe(q);
    await expect(page.locator('.selection-card')).toHaveCount(3);
    await page.waitForLoadState('networkidle');
    await page.locator('.catalogue-filters select[name="parent"]').selectOption({ index: 1 });
    await page.locator('.catalogue-filters select[name="category"]').selectOption({ index: 1 });
    await page.locator('.catalogue-filters button[type="submit"]').click();
    await expect(page).toHaveURL(/category=/);
    expect(new URL(page.url()).searchParams.get('q')).toBe(q);
    expect(new URL(page.url()).searchParams.get('sort')).toBe('name');
    await expect(page.locator('.selection-card')).toHaveCount(3);
    await page.locator('.catalogue-filters .filter-reset').click();
    await expect(page.locator('.selection-card')).toHaveCount(7);
    await page.waitForLoadState('networkidle');
    await page.locator('.catalogue-search input[name="q"]').fill('zzzz-unmatched-material');
    await page.locator('.catalogue-search button').click();
    await expect(page.locator('.editorial-empty')).toBeVisible();
    await expect(page.locator('.results-count')).toContainText('0');
    await expect(page.locator('.selection-card')).toHaveCount(0);
    await page.locator('.editorial-empty a').click();
    await expect(page.locator('.selection-card')).toHaveCount(7);
    await page.waitForLoadState('networkidle');

    for (const width of [320, 390, 768, 1024, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    const swipeControls = page.locator('.category-swipe-controls button');
    await expect(swipeControls.first()).toBeDisabled();
    await swipeControls.last().click();
    await expect(swipeControls.last()).toBeDisabled();
    await swipeControls.first().click();
    await expect(swipeControls.first()).toBeDisabled();
    const trigger = page.locator('.mobile-filter-toggle');
    await trigger.click();
    await expect(page.locator('.filter-dialog')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('.filter-dialog')).not.toBeVisible();
    await expect(trigger).toBeFocused();
    await page.locator('.catalogue-view-switch button').nth(1).click();
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.locator('.selection-card h2 a').first().click();
    await expect(page).toHaveURL(new RegExp(`/${locale}/products/[^?]+$`));
    await expect(page.locator('.product-detail h1')).toBeVisible();
    expect(errors).toEqual([]);
  });
}

test('catalogue remains searchable and filterable without JavaScript', async ({ browser }) => {
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  await page.goto('/fr/products');
  await expect(page.locator('.selection-card')).toHaveCount(7);
  await page.locator('.catalogue-category-swiper a').nth(1).click();
  await expect(page).toHaveURL(/category=/);
  await expect(page.locator('.selection-card')).toHaveCount(7);
  await page.locator('.catalogue-search input[name="q"]').fill('Noir');
  await page.locator('.catalogue-search button').click();
  await expect(page.locator('.selection-card')).toHaveCount(3);
  await page.locator('.catalogue-filters select[name="parent"]').selectOption({ index: 1 });
  await page.locator('.catalogue-filters button[type="submit"]').click();
  await expect(page.locator('.selection-card')).toHaveCount(3);
  await page.locator('.catalogue-sort select').selectOption('name');
  await page.locator('.catalogue-sort button[type="submit"]').click();
  await expect(page).toHaveURL(/sort=name/);
  await expect(page.locator('.selection-card')).toHaveCount(3);
  await context.close();
});

for (const locale of ['fr', 'ar']) {
  test(`${locale}: project photo banner and explicit gallery links remain usable on mobile`, async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.setViewportSize({ width: 618, height: 668 });
    await page.goto(`/${locale}/projects`);
    const banner = page.locator('.portfolio-hero');
    const photo = await banner.locator('.hero-media').boundingBox();
    const heading = await banner.locator('h1').boundingBox();
    expect(heading!.y).toBeGreaterThan(photo!.y);
    expect(heading!.y + heading!.height).toBeLessThan(photo!.y + photo!.height);
    await expect(banner.locator('h1')).toHaveCSS('color', 'rgb(255, 255, 255)');
    await expect(page.locator('.service-announcement button')).toHaveCount(0);
    await expect(page.locator('.announcement-delivery')).toBeVisible();
    await expect(page.locator('.project-gallery-link')).toHaveCount(5);
    for (const width of [320, 390, 618, 768, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    const link = page.locator('.project-gallery-link').first();
    const href = await link.getAttribute('href');
    await link.click();
    await expect(page).toHaveURL(new RegExp(`${href}$`));
    await expect(page.locator('.project-gallery')).toBeVisible();
  });
}
