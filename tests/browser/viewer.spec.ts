import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { viewerCopy } from '../../apps/web/src/lib/viewer';
const api = process.env.QA_API_URL || 'http://127.0.0.1:8101';
const php = process.env.QA_PHP_BINARY || 'php';
const fixtureRoot = resolve(process.env.LARAVEL_STORAGE_PATH || 'apps/api/storage', 'framework/testing/m3-browser');
const output = resolve('.tools/m6/screenshots'); mkdirSync(output, { recursive: true });
// Software WebGL is explicit in this QA project; this is not a physical-device benchmark.
test.use({ launchOptions: { args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader'] } });
test.setTimeout(90000);
function fixtures(mode: string) { execFileSync(php, ['apps/api/tests/Support/viewer-browser.php', mode, '--env=testing'], { cwd: resolve('.'), env: process.env, timeout: 60000, stdio: 'pipe' }); }
test.beforeAll(() => fixtures('add'));
test.afterAll(() => fixtures('record-upload'));
async function ready(page: import('@playwright/test').Page) { await expect(page.locator('[data-viewer-host]')).toHaveAttribute('data-viewer-ready', 'true', { timeout: 20000 }); await expect(page.locator('[data-viewer-host] canvas')).toBeVisible(); }
for (const locale of ['fr', 'ar']) {
  test(`${locale}: runtime/model lazy loading, actual GLB pixels, orbit, zoom, reset and optional dimension guides`, async ({ page }) => {
    await page.addInitScript(() => {
      const state = window as typeof window & { viewerCspViolations: string[] };
      state.viewerCspViolations = [];
      document.addEventListener('securitypolicyviolation', event => state.viewerCspViolations.push(`${event.violatedDirective}: ${event.blockedURI}`));
    });
    const copy = viewerCopy(locale), modelRequests: string[] = [], scripts: Promise<string>[] = [];
    page.on('request', request => { if (/\.glb(?:\?|$)/.test(request.url())) modelRequests.push(request.url()); });
    page.on('response', response => { if (response.request().resourceType() === 'script') scripts.push(response.text().catch(() => '')); });
    const metrics = [];
    for (const path of ['', '/products', '/products/temporary-product', '/products/synthetic-glb']) {
      await page.goto(`/${locale}${path}`);
      if (path.startsWith('/products/')) {
        const metric = await page.evaluate(async () => { const entries: number[] = []; let cls = 0; const lcp = new PerformanceObserver(list => { for (const e of list.getEntries()) entries.push(e.startTime); }); lcp.observe({ type: 'largest-contentful-paint', buffered: true }); const shifts = new PerformanceObserver(list => { for (const e of list.getEntries() as (PerformanceEntry & { value: number; hadRecentInput: boolean })[]) if (!e.hadRecentInput) cls += e.value; }); shifts.observe({ type: 'layout-shift', buffered: true }); await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); lcp.disconnect(); shifts.disconnect(); return { lcp_ms: Math.max(0, ...entries), cls, scripts: performance.getEntriesByType('resource').filter(e => e.name.includes('.js')).length }; });
        metrics.push({ path, ...metric });
      }
      expect(modelRequests).toEqual([]); expect((await Promise.all(scripts)).some(body => body.includes('THREE.WebGLRenderer'))).toBe(false);
    }
    writeFileSync(`.tools/m6/performance-${locale}.json`, JSON.stringify(metrics, null, 2));
    const trigger = page.getByRole('button', { name: copy.open, exact: true }); await trigger.click(); await ready(page);
    expect(await page.evaluate(() => (window as typeof window & { viewerCspViolations: string[] }).viewerCspViolations)).toEqual([]);
    expect(modelRequests).toHaveLength(1); expect((await Promise.all(scripts)).some(body => body.includes('THREE.WebGLRenderer'))).toBe(true);
    const host = page.locator('[data-viewer-host]'), canvas = host.locator('canvas');
    expect(Number(await host.getAttribute('data-viewer-triangles'))).toBeGreaterThan(2);
    const before = await canvas.screenshot(); const camera = () => host.getAttribute('data-viewer-camera'); const initial = await camera();
    await canvas.focus(); await canvas.press('ArrowLeft'); await expect.poll(camera).not.toBe(initial);
    const left = JSON.parse((await camera())!); expect(left[0]).toBeLessThan(JSON.parse(initial!)[0]);
    expect(Buffer.compare(before, await canvas.screenshot())).not.toBe(0);
    await canvas.focus(); await canvas.press('Home'); await expect.poll(camera).toBe(initial);
    await canvas.focus(); await page.keyboard.press('ArrowRight'); await expect.poll(camera).not.toBe(initial);
    await canvas.focus(); await canvas.press('Home'); await expect.poll(camera).toBe(initial);
    const distance = (values: number[]) => Math.hypot(...values), baseDistance = distance(JSON.parse(initial!));
    for (let i = 0; i < 12; i++) { await canvas.focus(); await canvas.press('+'); }
    expect(distance(JSON.parse((await camera())!))).toBeGreaterThanOrEqual(baseDistance * .649);
    for (let i = 0; i < 18; i++) { await canvas.focus(); await canvas.press('-'); }
    expect(distance(JSON.parse((await camera())!))).toBeLessThanOrEqual(baseDistance * 3.501);
    await canvas.focus(); await canvas.press('Home');
    const frames = await host.getAttribute('data-viewer-frames'); await page.waitForTimeout(700); expect(await host.getAttribute('data-viewer-frames')).toBe(frames);
    await expect(page.locator('.viewer-toolbar, .viewer-help, .viewer-disclosure')).toHaveCount(0);
    // This uploaded GLB has no admin-entered product dimensions; its bounding box
    // must not be presented as verified measurements. Procedural sizes below do.
    await expect(page.locator('.viewer-dimension-overlay svg')).toHaveCount(0);
    await page.locator('.viewer-panel').screenshot({ path: resolve(output, `${locale}-glb-desktop.png`) });
    await page.getByRole('button', { name: copy.photos, exact: true }).click(); await expect(trigger).toBeFocused(); await expect(page.locator('[data-product-image]')).toBeVisible(); await expect(canvas).toHaveCount(0);
  });

  test(`${locale}: procedural sizes update real proportions, mobile/RTL, touch scroll and M7 context`, async ({ page }) => {
    const copy = viewerCopy(locale);
    for (const width of [360, 390, 430, 768, 1440]) {
      await page.setViewportSize({ width, height: 844 }); await page.goto(`/${locale}/products/synthetic-slab`); await page.getByRole('button', { name: copy.open, exact: true }).click(); await ready(page);
      const host = page.locator('[data-viewer-host]'); expect(JSON.parse((await host.getAttribute('data-viewer-dimensions'))!)).toEqual({ width: .07, length: .2, depth: .02 });
      await page.locator('.viewer-size select').selectOption({ label: 'Mapped size B' }); await expect.poll(async () => JSON.parse((await host.getAttribute('data-viewer-dimensions'))!)).toEqual({ width: .1, length: .3, depth: .015 });
      await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr'); expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      if (width < 768) await expect(host.locator('canvas')).toHaveCSS('touch-action', 'none');
      await expect(page.locator('.viewer-dimension-overlay svg')).toBeVisible();
      await expect(page.locator('.viewer-toolbar')).toHaveCount(0);
      await page.locator('.viewer-panel').screenshot({ path: resolve(output, `${locale}-procedural-${width}.png`) });
      await page.getByRole('button', { name: copy.photos, exact: true }).click();
    }
    await page.locator('.request-material > button').click(); const dialog = page.locator('.request-material dialog'); await expect(dialog.locator('[name=size]')).toHaveValue(await page.locator('[data-size-id][aria-pressed=true]').getAttribute('data-size-id') as string); await dialog.locator('[name=quantity]').fill('2'); await dialog.locator('button[type=submit]').click(); await expect(dialog.locator('.request-review')).toBeVisible(); expect(await dialog.locator('.request-review textarea').inputValue()).toContain('Mapped size B');
    await page.keyboard.press('Escape');
    await page.goto(`/${locale}/products/synthetic-block`); await page.getByRole('button', { name: copy.open, exact: true }).click(); await ready(page); expect(JSON.parse((await page.locator('[data-viewer-host]').getAttribute('data-viewer-dimensions'))!)).toEqual({ width: .6, length: .4, depth: .2 });
  });

  test(`${locale}: failed GLB and unavailable WebGL retain usable photos and recovery`, async ({ page }) => {
    const copy = viewerCopy(locale); await page.goto(`/${locale}/products/synthetic-glb`);
    await page.route('**/models/**/*.glb', route => route.abort()); await page.getByRole('button', { name: copy.open, exact: true }).click(); await expect(page.locator('.viewer-fallback [role=alert]')).toHaveText(copy.unavailable, { timeout: 20000 });
    await page.unroute('**/models/**/*.glb'); await page.getByRole('button', { name: copy.retry, exact: true }).click(); await ready(page);
    await page.getByRole('button', { name: copy.photos, exact: true }).click(); await expect(page.locator('[data-product-image]')).toBeVisible();
    await page.addInitScript(() => { const original = HTMLCanvasElement.prototype.getContext; HTMLCanvasElement.prototype.getContext = function (type: string, ...args: unknown[]) { if (type.startsWith('webgl')) return null; return original.apply(this, [type, ...args] as Parameters<typeof original>); } as typeof original; });
    await page.reload(); await page.getByRole('button', { name: copy.open, exact: true }).click(); await expect(page.locator('.viewer-fallback [role=alert]')).toHaveText(copy.webgl, { timeout: 20000 }); await page.getByRole('button', { name: copy.photos, exact: true }).click(); await expect(page.locator('[data-product-image]')).toBeVisible();
  });

  test(`${locale}: touch phone and tablet load GLB, opt into orbit and release page scrolling`, async ({ browser }) => {
    const copy = viewerCopy(locale);
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, deviceScaleFactor: 2, reducedMotion: 'reduce', baseURL: process.env.QA_BASE_URL || 'http://127.0.0.1:3101' });
    const page = await context.newPage();
    try {
      for (const width of [390, 768]) {
        await page.setViewportSize({ width, height: 844 }); await page.goto(`/${locale}/products/synthetic-glb`);
        await page.getByRole('button', { name: copy.open, exact: true }).tap(); await ready(page);
        const canvas = page.locator('[data-viewer-host] canvas'), host = page.locator('[data-viewer-host]');
        await expect(canvas).toHaveCSS('touch-action', 'none');
        await canvas.scrollIntoViewIfNeeded(); const box = (await canvas.boundingBox())!, before = await host.getAttribute('data-viewer-camera');
        const session = await context.newCDPSession(page), x = box.x + box.width / 2, y = box.y + box.height / 2;
        await session.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y }] });
        for (let step = 1; step <= 6; step++) {
          await session.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: x + step * 8, y: y + step * 2 }] });
          await page.evaluate(() => new Promise(resolve => requestAnimationFrame(resolve)));
        }
        await session.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] }); await session.detach();
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        await expect.poll(() => host.getAttribute('data-viewer-camera')).not.toBe(before);
        await expect(page.locator('.viewer-touch-toggle')).toHaveCount(0);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.locator('.viewer-panel').screenshot({ path: resolve(output, `${locale}-glb-touch-${width}.png`) });
        await page.getByRole('button', { name: copy.photos, exact: true }).tap(); await expect(canvas).toHaveCount(0);
      }
    } finally { await context.close(); }
  });
}

test('authenticated private admin preview loads the shared real renderer; upload stays private', async ({ page, request }) => {
  test.setTimeout(300000); page.setDefaultNavigationTimeout(60000);
  const manifest = JSON.parse(readFileSync(resolve(fixtureRoot, 'fixture-manifest.json'), 'utf8'));
  expect((await request.get(`${api}/admin/product-viewer/${manifest.viewer_private_product}`, { headers: { Accept: 'application/json' } })).status()).toBe(401);
  await page.goto(`${api}/admin/login`); await page.locator('input[type=email]').fill('viewer-qa@example.invalid'); await page.locator('input[type=password]').fill('SyntheticOnly-M6-Only1!'); await page.locator('button[type=submit]').click(); await expect(page).not.toHaveURL(/login$/, { timeout: 60000 });
  await page.goto(`${api}/admin/product-viewer/${manifest.viewer_private_product}?locale=ar`); await page.getByRole('button', { name: viewerCopy('ar').open, exact: true }).click(); await ready(page); expect(Number(await page.locator('[data-viewer-host]').getAttribute('data-viewer-triangles'))).toBeGreaterThan(2);
  await page.screenshot({ path: resolve(output, 'admin-private-model-ar.png'), fullPage: true });
  await page.goto(`${api}/admin/media-assets`); await page.getByRole('button', { name: 'Upload product GLB', exact: true }).click(); await page.getByRole('dialog').locator('.filepond--root input[type=file]').setInputFiles(resolve(fixtureRoot, 'synthetic-admin-upload.glb'));
  const dialog = page.getByRole('dialog'); await expect(dialog.locator('.filepond--item')).toBeVisible(); await expect(dialog.locator('.filepond--item')).toHaveAttribute('data-filepond-item-state', 'processing-complete', { timeout: 120000 }); await dialog.locator('button[type=submit]').click(); await expect(dialog).not.toBeVisible({ timeout: 60000 });
  fixtures('verify-upload');
  await page.getByRole('searchbox').fill('synthetic-admin-upload.glb');
  await expect(page.locator('table').getByText('synthetic-admin-upload.glb', { exact: true })).toBeVisible({ timeout: 30000 });
});
