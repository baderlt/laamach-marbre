import { test, expect } from '@playwright/test';
const api = process.env.QA_API_URL || 'http://127.0.0.1:8101';
import { viewerCopy } from '../../apps/web/src/lib/viewer';

test.use({ launchOptions: { args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader'] } });
test.setTimeout(90000);

for (const locale of ['fr', 'ar']) {
  test(`${locale}: every published material only shows its configured measurements and unique technical labels`, async ({ page, request }) => {
    const response = await request.get(`${api}/api/v1/products?locale=${locale}&per_page=24`);
    const products = (await response.json()).data;
    expect(products.length).toBeGreaterThanOrEqual(7);
    for (const product of products) {
      await page.goto(`/${locale}/products/${product.slug}`);
      const example = page.locator('.dimension-example');
      const detail = (await (await request.get(`${api}/api/v1/products/${product.slug}?locale=${locale}`)).json()).data;
      const size = detail.sizes[0];
      const axes = size ? [size.width, size.length, size.thickness].filter(value => Number(value) > 0).length : 0;
      await expect(example).toHaveCount(axes ? 1 : 0);
      if (axes) {
        await expect(example).toBeVisible();
        await expect(example.locator('[data-dimension-axis]')).toHaveCount(axes);
        await expect(example).toContainText(locale === 'ar' ? 'أبعاد المنتج' : 'Dimensions du produit');
      }
      const labels = await page.locator('.facts-table dt').allTextContents();
      expect(new Set(labels.map(label => label.trim().toLocaleLowerCase(locale))).size).toBe(labels.length);
      await expect(page.locator('[data-size-id]')).toHaveCount(detail.sizes.length);
    }
    await page.goto(`/${locale}`);
    const nav = page.locator('.home-usage-links');
    await expect(nav.locator('a')).toHaveCount(5);
    const footer = page.locator('.home-projects-footer');
    await expect(page.locator('.home-projects .project-grid')).toHaveCount(0);
    expect(await nav.evaluate(element => !!(element.compareDocumentPosition(element.parentElement!.querySelector('.home-projects-footer')!) & Node.DOCUMENT_POSITION_FOLLOWING))).toBe(true);
    await expect(footer.locator('a')).toHaveAttribute('href', `/${locale}/projects`);
    for (const width of [320, 390, 492, 768, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await expect(nav).toHaveCSS('overflow-x', 'visible');
    }
  });

  test(`${locale}: compact 3D studio, dimension tracking, hint dismissal and keyboard recovery`, async ({ page, request }) => {
    const copy = viewerCopy(locale), models: string[] = [], errors: string[] = [];
    const detail = (await (await request.get(`${api}/api/v1/products/grey-bnikhiran?locale=${locale}`)).json()).data;
    const axes = [detail.sizes[0].width, detail.sizes[0].length, detail.sizes[0].thickness].filter(value => Number(value) > 0).length;
    page.on('request', request => { if (/\.glb(?:\?|$)/.test(request.url())) models.push(request.url()); });
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`/${locale}/products/grey-bnikhiran`);
    expect(models).toHaveLength(0);
    await page.locator('[data-viewer-activate]').click();
    const host = page.locator('[data-viewer-host]');
    await expect(host).toHaveAttribute('data-viewer-ready', 'true', { timeout: 20000 });
    const canvas = host.locator('canvas');
    await canvas.scrollIntoViewIfNeeded();
    await expect(page.locator('.viewer-toolbar, .viewer-help, .viewer-disclosure')).toHaveCount(0);
    await expect(page.locator('.viewer-gesture-hint')).toBeVisible();
    await expect(page.locator('.viewer-dimension-overlay [data-dimension-axis]')).toHaveCount(axes);
    await expect(page.locator('.viewer-dimension-caption')).toHaveText(copy.measuredDimensions);
    const lines = page.locator('.viewer-dimension-overlay .dimension-line');
    const original = await lines.first().getAttribute('d');
    const camera = await host.getAttribute('data-viewer-camera');
    const bounds = (await canvas.boundingBox())!;
    await page.mouse.move(bounds.x + bounds.width / 2, bounds.y + bounds.height / 2);
    await page.mouse.down();
    await page.mouse.move(bounds.x + bounds.width / 2 + 60, bounds.y + bounds.height / 2 + 10, { steps: 8 });
    await page.mouse.up();
    await expect(page.locator('.viewer-gesture-hint')).toHaveCount(0);
    await expect.poll(() => host.getAttribute('data-viewer-camera')).not.toBe(camera);
    await canvas.focus();
    await canvas.press('Home');
    await canvas.press('ArrowRight');
    await expect(host).toHaveAttribute('data-viewer-interacted', 'true');
    await expect(page.locator('.viewer-gesture-hint')).toHaveCount(0);
    await expect.poll(() => host.getAttribute('data-viewer-camera')).not.toBe(camera);
    await expect.poll(() => lines.first().getAttribute('d')).not.toBe(original);
    await canvas.press('Home');
    await expect.poll(() => host.getAttribute('data-viewer-camera')).toBe(camera);
    const frames = await host.getAttribute('data-viewer-frames');
    await page.waitForTimeout(400);
    expect(await host.getAttribute('data-viewer-frames')).toBe(frames);
    await page.getByRole('button', { name: copy.photos, exact: true }).click();
    await expect(page.locator('[data-viewer-activate]')).toBeFocused();
    await expect(canvas).toHaveCount(0);

    await page.setViewportSize({ width: 390, height: 844 });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.reload();
    await page.locator('[data-viewer-activate]').click();
    await expect(host).toHaveAttribute('data-viewer-ready', 'true', { timeout: 20000 });
    await expect(canvas).toHaveCSS('touch-action', 'none');
    await expect(page.locator('.gesture-hand')).toHaveCSS('animation-name', 'none');
    await expect(page.locator('.viewer-touch-toggle, .viewer-interaction-footer, .dimension-extension')).toHaveCount(0);
    await expect(canvas).toHaveCSS('touch-action', 'none');
    await canvas.focus();
    await canvas.press('ArrowLeft');
    await expect(page.locator('.viewer-gesture-hint')).toHaveCount(0);
    await expect(canvas).toHaveCSS('touch-action', 'none');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await canvas.focus();
    await canvas.press('Escape');
    await expect(page.locator('[data-viewer-activate]')).toBeFocused();
    expect(errors).toEqual([]);
  });
}
