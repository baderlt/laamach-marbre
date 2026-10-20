import { test, expect } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { execFileSync } from 'node:child_process';

const contactFixture = (mode: string) => execFileSync(process.env.QA_PHP_BINARY || 'php', ['apps/api/tests/Support/catalogue-browser.php', mode, '--env=testing'], { stdio: 'pipe' });
test.beforeAll(() => contactFixture('contact-off'));
test.afterAll(() => contactFixture('contact-on'));

const routes = ['', '/products', '/products/temporary-product', '/projects', '/projects/synthetic-project', '/services', '/about', '/contact'];
const widths = [320, 360, 375, 390, 430, 768, 1024, 1280, 1440];
const output = resolve('.tools/m5-screenshots');

for (const locale of ['fr', 'ar']) {
  test(`${locale}: all eight final pages at nine widths, safe media and screenshot comparison`, async ({ page }) => {
    test.setTimeout(180000);
    await mkdir(output, { recursive: true });
    const forbidden: string[] = []; const external: string[] = []; const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => {
      const url = new URL(request.url());
      if (url.protocol.startsWith('http') && !['127.0.0.1', 'localhost'].includes(url.hostname)) external.push(url.href);
      if (/\.(glb|gltf|ktx2|hdr)(\?|$)|\/three[.-]|wa\.me|api\.whatsapp|originals\//i.test(url.href)) forbidden.push(url.href);
    });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    for (const route of routes) {
      expect((await page.goto(`/${locale}${route}`))?.status()).toBe(200);
      await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
      await expect(page.locator('main h1')).toHaveCount(1);
      for (const width of widths) {
        await page.setViewportSize({ width, height: 1000 });
        await page.evaluate(() => new Promise<void>(resolve => requestAnimationFrame(() => resolve())));
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${locale}${route} at ${width}`).toBe(true);
        await expect(page.locator('video[autoplay]')).toHaveCount(0);
        if (width === 1440 || width === 390) {
          await page.evaluate(async () => {
            await document.fonts.ready;
            for (let position = 0; position < document.documentElement.scrollHeight; position += innerHeight) {
              window.scrollTo(0, position);
              await new Promise<void>(resolve => requestAnimationFrame(() => resolve()));
            }
            window.scrollTo(0, 0);
            await Promise.all(Array.from(document.images).filter(image => image.complete && image.naturalWidth > 0).map(image => image.decode().catch(() => undefined)));
          });
          await page.screenshot({ path: resolve(output, `${locale}-${route.slice(1).replaceAll('/', '-') || 'home'}-${width}.png`), fullPage: true, animations: 'disabled' });
        }
      }
    }
    expect(forbidden).toEqual([]); expect(external).toEqual([]); expect(errors).toEqual([]);
  });

  test(`${locale}: mobile navigation, focus, filter sheet, two-column cards and search`, async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/${locale}`);
    const menu = page.locator('.menu-toggle');
    await menu.click(); await expect(page.locator('.mobile-menu')).toBeVisible();
    await page.keyboard.press('Escape'); await expect(page.locator('.mobile-menu')).not.toBeVisible(); await expect(menu).toBeFocused();
    await page.goto(`/${locale}/products`);
    const cards = page.locator('.product-grid article'); await expect(cards).toHaveCount(2);
    const first = await cards.nth(0).boundingBox(); const second = await cards.nth(1).boundingBox();
    expect(Math.abs(first!.y - second!.y)).toBeLessThan(2);
    await page.locator('.mobile-filter-toggle').click(); await expect(page.locator('.filter-dialog')).toBeVisible();
    await page.keyboard.press('Escape'); await expect(page.locator('.filter-dialog')).not.toBeVisible();
    await page.locator('.catalogue-search input[type=search]').fill('no-matching-material');
    await page.locator('.catalogue-search button').click();
    await expect(page).toHaveURL(/q=no-matching-material/); await expect(cards).toHaveCount(0);
    const otherLocale = locale === 'ar' ? 'fr' : 'ar';
    await expect(page.locator(`.header-language-switch a[lang=${otherLocale}]`)).toHaveAttribute('href', `/${otherLocale}/products?q=no-matching-material`);
  });

  test(`${locale}: editorial SEO, honest contact empty state and 3D disabled until explicitly configured`, async ({ page }) => {
    await page.goto(`/${locale}/about`); await expect(page).toHaveTitle('LAAMACH MARBRE — QA about');
    const hero = page.locator('.hero-media img');
    await expect(hero).toBeVisible();
    await expect.poll(() => hero.evaluate(node => (node as HTMLImageElement).naturalWidth)).toBeGreaterThan(0);
    await page.goto(`/${locale}/contact`); await expect(page.locator('.contact-form button')).toBeDisabled();
    await expect(page.locator('a[href*="wa.me"], a[href*="whatsapp"], iframe')).toHaveCount(0);
    await page.goto(`/${locale}/products/temporary-product`); await expect(page.locator('[data-viewer-activate]')).toHaveCount(0); await expect(page.locator('[data-product-image]')).toBeVisible();
    await expect(page.locator('canvas, model-viewer')).toHaveCount(0);
    await page.setViewportSize({ width: 390, height: 844 });
    const gallery = await page.locator('.product-visual-column').boundingBox();
    const facts = await page.locator('.product-facts').boundingBox();
    const support = await page.locator('.product-support-column').boundingBox();
    expect(facts!.y).toBeGreaterThanOrEqual(gallery!.y + gallery!.height);
    expect(support!.y).toBeGreaterThanOrEqual(facts!.y + facts!.height);
  });
}
