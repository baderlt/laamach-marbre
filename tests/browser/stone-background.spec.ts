import { test, expect } from '@playwright/test';

for (const locale of ['fr', 'ar']) {
  test(`${locale}: stone atmosphere preserves public pages, media and responsive controls`, async ({ page }) => {
    test.setTimeout(120000);
    const errors: string[] = [];
    const forbidden: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => { if (/\.glb(?:\?|$)|\/originals\//.test(request.url())) forbidden.push(request.url()); });
    await page.goto(`/${locale}/products`);
    const product = await page.locator('.product-card a[href*="/products/"]').first().getAttribute('href');
    await page.goto(`/${locale}/projects`);
    const project = await page.locator('.project-card a[href*="/projects/"]').first().getAttribute('href');
    expect(product).toBeTruthy(); expect(project).toBeTruthy();
    for (const route of [`/${locale}`, `/${locale}/products`, product!, `/${locale}/projects`, project!, `/${locale}/about`, `/${locale}/services`, `/${locale}/contact`]) {
      expect((await page.goto(route))?.status()).toBe(200);
      await expect(page.locator('.stone-background')).toHaveCount(1);
      await expect(page.locator('.stone-background')).toHaveAttribute('aria-hidden', 'true');
      await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
      for (const width of [360,390,430,768,1024,1440]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${route} ${width}`).toBe(true);
      }
      await expect(page.locator('video[autoplay]')).toHaveCount(0);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/${locale}/products`);
    await page.locator('.menu-toggle').click(); await expect(page.locator('.mobile-menu')).toBeVisible();
    await page.keyboard.press('Escape'); await expect(page.locator('.menu-toggle')).toBeFocused();
    await page.locator('.mobile-filter-toggle').click(); await expect(page.locator('.filter-dialog')).toBeVisible();
    await page.keyboard.press('Escape');
    await page.locator('.catalogue-search input[type=search]').fill('no-matching-stone-qa');
    await page.locator('.catalogue-search button').click();
    await expect(page.locator('.product-card')).toHaveCount(0);
    await page.goto(product!);
    await page.locator('.gallery-zoom').click(); await expect(page.locator('dialog[open]')).toBeVisible();
    await page.keyboard.press('Escape');
    // Products without assigned finishes use the supported project-application flow.
    const request = page.locator('.request-material > button, .request-application > button').first();
    if (await request.count()) {
      await request.click(); await expect(page.locator('.request-entry dialog[open]')).toBeVisible();
      await page.keyboard.press('Escape');
    } else {
      await expect(page.locator('.request-material a[href$="/contact"]')).toBeVisible();
    }
    expect(errors).toEqual([]); expect(forbidden).toEqual([]);
  });
}

test('pointer easing settles, cancels offscreen/hidden, and responds to preference changes', async ({ page }) => {
  await page.addInitScript(() => {
    const original = window.requestAnimationFrame;
    const tracker = window as typeof window & { stoneRafCalls: number };
    tracker.stoneRafCalls = 0;
    window.requestAnimationFrame = callback => { tracker.stoneRafCalls++; return original(callback); };
  });
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/fr');
  const background = page.locator('.stone-background');
  const focus = page.locator('.stone-focus');
  await expect(background).toHaveAttribute('data-interactive', 'true');
  const rootStyle = await background.getAttribute('style');
  await page.mouse.move(1000, 350);
  await expect(background).toHaveAttribute('data-active', 'true');
  await expect.poll(() => focus.evaluate(e => new DOMMatrix(getComputedStyle(e).transform).m41)).toBeGreaterThan(760);
  expect(await background.getAttribute('style')).toBe(rootStyle);
  await page.waitForTimeout(1200);
  const settled = await focus.getAttribute('style');
  const frameCount = await page.evaluate(() => (window as typeof window & { stoneRafCalls: number }).stoneRafCalls);
  await page.waitForTimeout(400); expect(await focus.getAttribute('style')).toBe(settled);
  expect(await page.evaluate(() => (window as typeof window & { stoneRafCalls: number }).stoneRafCalls)).toBe(frameCount);
  await page.mouse.move(1100, 400);
  await page.evaluate(() => { Object.defineProperty(document, 'hidden', { configurable: true, value: true }); document.dispatchEvent(new Event('visibilitychange')); });
  await expect(background).toHaveAttribute('data-active', 'false');
  await page.evaluate(() => { delete (document as unknown as { hidden?: boolean }).hidden; document.dispatchEvent(new Event('visibilitychange')); });
  await page.locator('.collection-grid').evaluate(element => element.scrollIntoView({ block: 'start', behavior: 'instant' }));
  await expect(background).toHaveAttribute('data-active', 'false');
  await page.mouse.move(1000, 350); await expect(background).toHaveAttribute('data-active', 'false');
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await expect(background).toHaveAttribute('data-interactive', 'false');
  await page.mouse.move(1200, 450); await expect(background).toHaveAttribute('data-active', 'false');
  await expect(page.locator('.stone-focus')).toBeHidden();
});

for (const mode of ['touch', 'tablet', 'reduced', 'save-data', 'low-memory', 'no-js', 'unsupported-effects']) {
  test(`${mode}: static photograph with no detail-layer download`, async ({ browser }) => {
    const size = { width: mode === 'touch' ? 390 : mode === 'tablet' ? 920 : 1440, height: 844 };
    const context = await browser.newContext({ viewport: size, screen: size, hasTouch: mode === 'touch', isMobile: mode === 'touch', reducedMotion: mode === 'reduced' || mode === 'tablet' ? 'reduce' : 'no-preference', javaScriptEnabled: mode !== 'no-js' });
    if (mode === 'save-data') await context.addInitScript(() => Object.defineProperty(navigator, 'connection', { value: { saveData: true, addEventListener() {}, removeEventListener() {} } }));
    if (mode === 'low-memory') await context.addInitScript(() => Object.defineProperty(navigator, 'deviceMemory', { value: 2 }));
    if (mode === 'unsupported-effects') await context.addInitScript(() => { const supports = CSS.supports.bind(CSS); CSS.supports = ((property: string, value?: string) => property === 'mask-image' ? false : value === undefined ? supports(property) : supports(property, value)) as typeof CSS.supports; });
    const page = await context.newPage(), requests: string[] = [];
    page.on('request', request => { if (request.url().includes('/stone-background/')) requests.push(request.url()); });
    await page.goto('/fr');
    await expect(page.locator('.stone-base')).toBeVisible();
    if (mode !== 'no-js') await expect(page.locator('.stone-background')).toHaveAttribute('data-interactive', 'false');
    await page.mouse.move(100, 300); await page.waitForTimeout(250);
    expect(requests.filter(url => /Detail-/.test(url))).toEqual([]);
    expect(requests).toHaveLength(1);
    expect(await page.locator('.stone-base').evaluate(e => getComputedStyle(e).backgroundImage)).toContain('/stone-background/');
    expect(await page.locator('.stone-background').evaluate(e => getComputedStyle(e).pointerEvents)).toBe('none');
    const response = await context.request.get(requests[0]);
    expect(response.ok()).toBe(true);
    expect(response.headers()['cache-control']).toContain('immutable');
    await context.close();
  });
}
