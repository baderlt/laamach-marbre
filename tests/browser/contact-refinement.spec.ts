import { test, expect } from '@playwright/test';

for (const locale of ['fr', 'ar']) {
  test(`${locale}: premium contact, actual contact channels, photo banners and mobile navigation`, async ({ page }) => {
    const errors: string[] = [], posts: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => { if (request.method() === 'POST') posts.push(request.url()); });
    await page.goto(`/${locale}/contact`);
    const form = page.locator('#contact-form');
    await expect(form.locator('fieldset')).toBeEnabled();
    await expect(page.locator('.contact-info-card')).toHaveCount(4);
    await expect(page.locator('.contact-proof')).toContainText('150');
    await expect(page.locator('.contact-proof')).toContainText('100');
    await expect(page.locator('.contact-proof')).toContainText('10');
    await expect(page.locator('.location-placeholder, input[type=file], iframe')).toHaveCount(0);
    await expect(page.locator('body')).not.toContainText('contact@laamachmarbre.com');
    await form.locator('[name=name]').fill(locale === 'ar' ? 'أمين' : 'Amine');
    await form.locator('[name=request]').selectOption({ index: 2 });
    await form.locator('[name=phone]').fill('+212600000000');
    await form.locator('[name=message]').fill(locale === 'ar' ? 'أرغب في مناقشة مشروع واجهة بالحجر الطبيعي.' : 'Je souhaite discuter d’une façade en pierre naturelle.');
    await form.locator('button[type=submit]').click();
    const prepared = form.locator('.contact-message-review a');
    await expect(prepared).toBeVisible();
    const link = new URL((await prepared.getAttribute('href'))!);
    expect(link.hostname).toBe('wa.me');
    expect(link.pathname).toBe('/212706795408');
    expect(link.searchParams.get('text')).toContain(locale === 'ar' ? 'أمين' : 'Amine');
    expect(posts).toEqual([]);
    for (const width of [320, 390, 492, 768, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    for (const kind of ['services', 'about']) {
      await page.setViewportSize({ width: 492, height: 668 });
      await page.goto(`/${locale}/${kind}`);
      const hero = page.locator('.photo-banner');
      await expect(hero.locator('.hero-media img')).toHaveAttribute('src', new RegExp(`/editorial/${kind}-`));
      await expect(hero.locator('.hero-media')).toHaveCSS('position', 'absolute');
      const banner = (await hero.boundingBox())!, title = (await hero.locator('h1').boundingBox())!;
      expect(title.y).toBeGreaterThanOrEqual(banner.y);
      expect(title.y + title.height).toBeLessThanOrEqual(banner.y + banner.height);
      await expect(hero.locator('.hero-copy')).toHaveCSS('color', 'rgb(255, 255, 255)');
    }
    await page.locator('.menu-toggle').click();
    const drawer = page.locator('.mobile-menu');
    await expect(drawer).toBeVisible();
    await expect(drawer.locator('.drawer-heading img')).toHaveAttribute('src', '/brand/laamach-final-logo.webp');
    await expect(drawer.locator('.drawer-menu-nav a')).toHaveCount(6);
    await expect(drawer.locator('.drawer-nav-icon')).toHaveCount(6);
    await expect(drawer.locator('.drawer-languages a[aria-current=page]')).toHaveAttribute('lang', locale);
    const language = drawer.locator('.drawer-languages');
    await expect(language).toHaveCSS('grid-template-columns', /.+ .+/);
    const quote = (await drawer.locator('.drawer-quote').boundingBox())!, content = (await drawer.locator('.drawer-content').boundingBox())!;
    expect(content.width - quote.width).toBeLessThanOrEqual(42);
    await page.keyboard.press('Tab');
    await expect(drawer.locator(':focus')).toHaveCount(1);
    await page.keyboard.press('Escape');
    await expect(page.locator('.menu-toggle')).toBeFocused();
    expect(errors).toEqual([]);
  });
}
