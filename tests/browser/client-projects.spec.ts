import { test, expect } from '@playwright/test';

for (const locale of ['fr', 'ar']) {
  test(`${locale}: real client projects, SEO and accessible mixed gallery`, async ({ page }) => {
    const errors: string[] = [];
    const privateRequests: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => { if (/\/originals\/|courtyard-v1/.test(request.url())) privateRequests.push(request.url()); });
    await page.goto(`/${locale}`);
    await expect(page.locator('.trust-grid')).toContainText('150');
    await expect(page.locator('.trust-grid')).toContainText('100');
    await expect(page.locator('.trust-grid')).toContainText('10');
    // Six approved usages include the subsequently added fireplace/barbecue projects.
    await expect(page.locator('.home-usage-links > a[href*="/projects?category="]')).toHaveCount(6);
    await page.goto(`/${locale}/projects/cascades-et-fontaines`);
    await expect(page.locator('meta[name="description"]')).toHaveAttribute('content', /.+/);
    await expect(page.locator('meta[property="og:image"]')).toHaveAttribute('content', /\/media\/images\//);
    await expect(page.locator('link[hreflang="ar"]')).toHaveAttribute('href', /\/ar\/projects\/cascades-et-fontaines$/);
    await expect(page.locator('.project-gallery-toolbar .project-view-previous').first()).toHaveCSS('position', 'static');
    await page.locator('.gallery-expand').click();
    await expect(page.locator('.project-lightbox')).toBeVisible();
    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('Tab');
    await expect(page.locator('.project-lightbox :focus')).toHaveCount(1);
    await page.keyboard.press('Escape');
    await expect(page.locator('.gallery-expand')).toBeFocused();
    const videos = page.locator('.project-gallery-choices').getByRole('button', { name: locale === 'ar' ? /^فيديو/ : /^Vidéo/ });
    await expect(videos).toHaveCount(5);
    await videos.first().click();
    const player = page.locator('.project-gallery-stage video');
    await expect(player).toHaveAttribute('controls', '');
    await expect(player).toHaveAttribute('preload', 'none');
    expect(await player.evaluate(element => (element as HTMLVideoElement).paused)).toBe(true);
    expect(await player.getAttribute('autoplay')).toBeNull();
    await page.locator('.gallery-expand').click();
    await expect(page.locator('.project-lightbox video')).toBeVisible();
    await page.keyboard.press('Escape');
    for (const width of [390, 430, 768, 1024, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    expect(errors).toEqual([]);
    expect(privateRequests).toEqual([]);
  });
}
