import { test, expect } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';

const base = process.env.QA_BASE_URL || 'http://127.0.0.1:3101';
const api = process.env.QA_API_URL || 'http://127.0.0.1:8101';

for (const locale of ['fr', 'ar']) {
  test(`${locale}: M8 metadata, launch gate, JSON-LD and populated responsive pages`, async ({ page }) => {
    test.setTimeout(360000);
    await page.addInitScript(() => {
      const state = window as typeof window & { cspViolations: string[] };
      state.cspViolations = [];
      document.addEventListener('securitypolicyviolation', event => state.cspViolations.push(`${event.violatedDirective}: ${event.blockedURI}`));
    });
    const errors: string[] = [], forbidden: string[] = [], scripts: Promise<string>[] = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => { if (/\.glb(?:\?|$)|\.mp4(?:\?|$)|\/originals\//.test(request.url())) forbidden.push(request.url()); });
    page.on('response', response => { if (response.request().resourceType() === 'script') scripts.push(response.text().catch(() => '')); });
    await page.goto(`/${locale}/products`);
    const product = await page.locator('.product-card a[href*="/products/"]').first().getAttribute('href');
    await page.goto(`/${locale}/projects`);
    const project = await page.locator('.project-card a[href*="/projects/"]').first().getAttribute('href');
    expect(product).toBeTruthy(); expect(project).toBeTruthy();
    const routes = [`/${locale}`, `/${locale}/products`, product!, `/${locale}/projects`, project!, `/${locale}/about`, `/${locale}/services`, `/${locale}/contact`];
    const titles = new Set<string>();
    await mkdir('.tools/m8/screenshots', { recursive: true });
    for (const route of routes) {
      const response = await page.goto(route);
      expect(response?.status()).toBe(200);
      expect(response!.headers()['x-robots-tag']).toContain('noindex');
      const csp = response!.headers()['content-security-policy-report-only'];
      expect(csp).toContain("script-src 'self' 'nonce-"); expect(csp).not.toContain('unsafe-eval');
      await expect(page.locator('main h1')).toHaveCount(1);
      await expect(page.locator('html')).toHaveAttribute('lang', locale);
      await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
      await expect(page.locator('link[rel=canonical]')).toHaveAttribute('href', `${base}${route}`);
      await expect(page.locator('meta[name=description]')).toHaveAttribute('content', /.+/);
      await expect(page.locator('meta[name=robots]')).toHaveAttribute('content', /noindex/);
      titles.add(await page.title());
      const structured = await page.locator('script[type="application/ld+json"]').evaluateAll(nodes => nodes.map(node => JSON.parse(node.textContent!)));
      expect(structured.some(item => item['@type'] === 'Organization')).toBe(true);
      for (const item of structured) { expect(item.offers).toBeUndefined(); expect(item.aggregateRating).toBeUndefined(); expect(item.review).toBeUndefined(); }
      const nonce = await page.locator('script[type="application/ld+json"]').first().evaluate(node => (node as HTMLScriptElement).nonce);
      expect(csp).toContain(`'nonce-${nonce}'`);
      for (const width of [320, 360, 390, 430, 768, 1024, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${route} at ${width}`).toBe(true);
        if (width === 390 || width === 1440) {
          await page.evaluate(async () => {
            await document.fonts.ready;
            for (let top = 0; top < document.documentElement.scrollHeight; top += innerHeight) { window.scrollTo(0, top); await new Promise(resolve => setTimeout(resolve, 40)); }
            window.scrollTo(0, 0);
            // Closed dialogs contain lazy images that intentionally never load.
            await Promise.all(Array.from(document.images).filter(image => image.complete).map(image => image.decode().catch(() => undefined)));
          });
          await page.screenshot({ path: resolve('.tools/m8/screenshots', `${route.slice(1).replaceAll('/', '-')}-${width}.png`), fullPage: true, animations: 'disabled' });
        }
      }
      await expect(page.locator('video[autoplay]')).toHaveCount(0);
      expect(await page.evaluate(() => (window as typeof window & { cspViolations: string[] }).cspViolations)).toEqual([]);
    }
    expect(titles.size).toBe(8);
    expect(errors).toEqual([]); expect(forbidden).toEqual([]);
    expect((await Promise.all(scripts)).some(body => body.includes('THREE.WebGLRenderer'))).toBe(false);
  });
}

test('M8 sitemap uses only eligible published URLs and robots remains launch-locked', async ({ request }) => {
  const source = await request.get(`${api}/api/v1/seo`); expect(source.ok()).toBe(true);
  const entries = (await source.json()).data as { path: string; locale: string }[];
  const sitemap = await request.get('/sitemap.xml'); expect(sitemap.ok()).toBe(true);
  const xml = await sitemap.text();
  for (const entry of entries) expect(xml).toContain(`<loc>${base}/${entry.locale}${entry.path ? `/${entry.path}` : ''}</loc>`);
  expect((xml.match(/<loc>/g) || []).length).toBe(entries.length);
  expect(xml).not.toMatch(/\/admin|\/api\/|\/private\/|draft|localhost/);
  expect(await (await request.get('/robots.txt')).text()).toContain('Disallow: /');
  expect((await request.get('/fr/products/does-not-exist')).status()).toBe(404);
  expect((await request.get('/ar/projects/does-not-exist')).status()).toBe(404);
});

test('M8 Host spoofing, cross-origin and oversized request bodies fail safely', async ({ request }) => {
  const spoof = await request.get('/fr', { headers: { host: 'spoof.invalid' } }); expect(spoof.status()).toBe(400);
  const cross = await request.post('/api/requests', { headers: { origin: 'https://spoof.invalid' }, data: {} }); expect(cross.status()).toBe(403);
  const large = await request.post('/api/requests', { headers: { origin: base }, data: { padding: 'x'.repeat(17000) } });
  expect(large.status()).toBe(413); expect(large.headers()['cache-control']).toContain('no-store');
  expect((await large.text()).includes('stack')).toBe(false);
});

test('M8 video lightbox creates and disposes its player only when expanded', async ({ page, request }) => {
  test.setTimeout(90000);
  const listing = (await (await request.get(`${api}/api/v1/projects?locale=fr&per_page=24`)).json()).data;
  let target: string | null = null, index = 0;
  for (const project of listing) {
    const detail = (await (await request.get(`${api}/api/v1/projects/${project.slug}?locale=fr`)).json()).data;
    index = detail.gallery.findIndex((item: { type: string }) => item.type === 'video');
    if (index !== -1) { target = detail.slug; break; }
  }
  expect(target).toBeTruthy();
  const videos: string[] = []; page.on('request', request => { if (/\.mp4(?:\?|$)/.test(request.url())) videos.push(request.url()); });
  await page.goto(`/fr/projects/${target}`);
  expect(videos).toEqual([]);
  await page.getByRole('button', { name: `Vidéo ${index + 1}`, exact: true }).click();
  await expect(page.locator('video')).toHaveCount(1);
  await expect(page.locator('video')).toHaveAttribute('preload', 'none');
  expect(videos).toEqual([]);
  await page.locator('.gallery-expand').click(); await expect(page.locator('video')).toHaveCount(2);
  await page.keyboard.press('Escape'); await expect(page.locator('video')).toHaveCount(1);
  await expect(page.locator('.gallery-expand')).toBeFocused();
});
