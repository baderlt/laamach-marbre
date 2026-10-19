import { test, expect } from '@playwright/test';
import stoneMedia from '../../apps/web/public/stone-background/manifest.json';

for (const locale of ['fr', 'ar']) {
  test(`${locale}: homepage image cards, transparent logo and responsive sections`, async ({ page }) => {
    await page.goto(`/${locale}`);
    await expect(page.locator('.provisional-notice')).toHaveCount(0);
    await expect(page.locator('.header-language-switch')).toBeVisible();
    await expect(page.locator('.header-language-switch a[aria-current="page"]')).toHaveAttribute('lang', locale);
    await expect(page.locator('header .client-logo')).toHaveAttribute('src', '/brand/laamach-final-logo.webp');
    await expect(page.locator('.hero-studies, .hero-material-note, .brand-activities')).toHaveCount(0);
    await expect(page.locator('header .client-logo')).toBeVisible();
    expect(await page.locator('header .client-logo').evaluate(image => getComputedStyle(image).backgroundColor)).toBe('rgba(0, 0, 0, 0)');
    await expect(page.locator('.material-values')).toHaveCount(0);
    const paths = page.locator('.page-hero > .hero-footer .hero-paths');
    await expect(paths.locator('a')).toHaveCount(2);
    await expect(paths.locator('ul, h2')).toHaveCount(0);
    await expect(paths.locator('img')).toHaveCount(2);
    for (const image of await paths.locator('img').all()) await expect.poll(() => image.evaluate(image => (image as HTMLImageElement).complete && (image as HTMLImageElement).naturalWidth > 0)).toBe(true);
    await expect(paths.locator('a').first()).toHaveAttribute('href', `/${locale}/projects`);
    await expect(paths.locator('a').nth(1)).toHaveAttribute('href', `/${locale}/products`);
    await expect(paths.locator('strong')).toHaveText(locale === 'ar' ? ['إنجاز المشاريع', 'توريد الحجر الطبيعي والرخام'] : ['Réalisation de projets', 'Fourniture de pierre & marbre']);
    await expect(paths.locator('small').first()).toHaveText(locale === 'ar' ? 'شاهد مشاريعنا' : 'Voir nos projets');
    await expect(page.locator('.home-projects .project-grid')).toHaveCount(0);
    await expect(page.locator('.home-usage-links a')).toHaveCount(5);
    expect(await page.locator('.page-hero').evaluate(hero => hero.lastElementChild?.classList.contains('hero-footer'))).toBe(true);
    await expect(page.locator('.collection-card > span')).toHaveText(locale === 'ar'
      ? ['ألواح الحجر الطبيعي', 'تركيب الحجر الطبيعي بالطريقة التقليدية', 'الواجهات والجدران', 'الشلالات والنوافير', 'الشوايات والمدافئ', 'الأرضيات التقليدية']
      : ['Tranches de pierre naturelle', 'Pierre naturelle : pose traditionnelle', 'Façades et murs', 'Cascades et fontaines', 'Barbecues et cheminées', 'Dallages traditionnels']);
    await expect(page.locator('.collection-grid').locator('..').locator('.section-heading .eyebrow')).toHaveCount(0);
    const card = page.locator('a.collection-card').first();
    await card.scrollIntoViewIfNeeded();
    await expect(card).toBeVisible();
    await expect(card.locator('span')).toBeVisible();
    expect(await card.locator('img').evaluate(image => getComputedStyle(image).position)).toBe('absolute');
    for (const width of [320, 390, 430, 768, 920, 1024, 1242, 1280, 1440, 1920]) {
      await page.setViewportSize({ width, height: 900 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      const language = await page.locator('.header-language-switch').boundingBox();
      const header = await page.locator('.header-main').boundingBox();
      expect(Math.abs(language!.y + language!.height / 2 - header!.y - header!.height / 2)).toBeLessThan(2);
      expect(header!.height).toBeLessThanOrEqual(width < 1280 ? 66 : 80);
      expect(language!.width).toBeLessThan(110);
      const projects = await paths.locator('a').first().boundingBox();
      const products = await paths.locator('a').nth(1).boundingBox();
      const nav = (await paths.boundingBox())!;
      expect(Math.abs(projects!.width - products!.width)).toBeLessThan(2);
      expect(Math.abs(projects!.height - products!.height)).toBeLessThan(2);
      const expected = width < 768 ? stoneMedia.variants.mobile.url : width < 1200 ? stoneMedia.variants.tablet.url : stoneMedia.variants.desktop.url;
      expect(await page.locator('.stone-base').evaluate(element => getComputedStyle(element).backgroundImage)).toContain(expected);
      expect(projects!.height).toBeGreaterThanOrEqual(44);
      expect(products!.height).toBeGreaterThanOrEqual(44);
      if (width >= 768) {
        expect(projects!.x).toBeLessThan(products!.x);
        expect(Math.abs(projects!.y - products!.y)).toBeLessThan(2);
        expect(Math.abs(projects!.x - nav.x - (nav.x + nav.width - products!.x - products!.width))).toBeLessThan(2);
        expect(Math.max(projects!.height, products!.height)).toBeLessThan(180);
      } else {
        expect(products!.y).toBeGreaterThanOrEqual(projects!.y + projects!.height - 1);
      }
      if (width < 1280) {
        const menu = await page.locator('.menu-toggle').boundingBox();
        expect(Math.min(Math.abs(menu!.x - language!.x - language!.width), Math.abs(language!.x - menu!.x - menu!.width))).toBeLessThanOrEqual(13);
      } else {
        const rail = (await page.locator('.brand-rail').boundingBox())!;
        const heading = (await page.locator('.hero-copy h1').boundingBox())!;
        expect(rail.x + rail.width <= heading.x || rail.x >= heading.x + heading.width).toBe(true);
      }
    }
    const image = card.locator('img');
    await card.hover();
    await expect.poll(async () => image.evaluate(element => getComputedStyle(element).transform)).not.toBe('none');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect.poll(async () => image.evaluate(element => getComputedStyle(element).transform)).toBe('none');
    await paths.scrollIntoViewIfNeeded();
    await expect(paths).toBeVisible();
    await paths.locator('a').first().click();
    await expect(page).toHaveURL(new RegExp(`/${locale}/projects$`));
    await expect(page.locator('.project-card').first()).toBeVisible();
    await page.goto(`/${locale}`);
    await expect(page.locator('.compact-grid .product-card').first()).toBeVisible();
    const trust = page.locator('.trust-grid .editorial-card').first();
    await trust.scrollIntoViewIfNeeded();
    await expect(trust).toBeVisible();
    expect(await trust.evaluate(element => getComputedStyle(element).borderRadius)).toBe('14px');
    await page.setViewportSize({ width: 1440, height: 1100 });
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.locator('.page-hero').screenshot({ path: test.info().outputPath('hero-desktop.png') });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.locator('.page-hero').screenshot({ path: test.info().outputPath('hero-mobile.png') });
  });
}

test('homepage content remains visible without JavaScript', async ({ browser }) => {
  const context = await browser.newContext({ javaScriptEnabled: false });
  const page = await context.newPage();
  await page.goto('/fr');
  await expect(page.locator('a.collection-card').first()).toBeVisible();
  await expect(page.locator('.hero-paths > a').first()).toBeVisible();
  await expect(page.locator('.hero-studies')).toHaveCount(0);
  await expect(page.locator('.compact-grid .product-card').first()).toBeVisible();
  await context.close();
});
