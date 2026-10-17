import { test, expect } from '@playwright/test';

for (const locale of ['fr', 'ar']) {
  test(`${locale}: project OR filters, cards and detail transfer no video before selection`, async ({ page }) => {
    const videos: string[] = [];
    const errors: string[] = [];
    page.on('request', request => { if (/\/videos\/.*\.mp4/.test(request.url())) videos.push(request.url()); });
    page.on('pageerror', error => errors.push(error.message));
    await page.setViewportSize({ width: 390, height: 844 });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto(`/${locale}/projects`);
    await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
    await expect(page.locator('[data-project-cards] article')).toHaveCount(2);
    await expect(page.locator('video, source')).toHaveCount(0);
    await page.locator('input[value=synthetic-usage]').check();
    await page.locator('form button').click();
    await expect(page.locator('[data-project-cards] article')).toHaveCount(1);
    await page.locator('input[value=second-usage]').check();
    await page.locator('form button').click();
    await expect(page.locator('[data-project-cards] article')).toHaveCount(2);
    expect(new URL(page.url()).searchParams.getAll('categories[]')).toHaveLength(2);
    await page.goto(`/${locale}/projects/synthetic-project`);
    await expect(page.locator('h1')).toContainText('Synthetic project');
    await expect(page.locator('.project-gallery .product-image img')).toBeVisible();
    await expect(page.locator('video')).toHaveCount(0);
    await expect(page.getByRole('heading', { name: locale === 'fr' ? 'Matériaux utilisés dans ce projet' : 'المواد المستخدمة في هذا المشروع' })).toBeVisible();
    await expect(page.locator('[data-materials-used] .product-grid article')).toHaveCount(1);
    await page.getByRole('button', { name: locale === 'fr' ? 'Photo 1' : 'صورة 1', exact: true }).click();
    await expect.poll(() => page.locator('.project-gallery .product-image img').evaluate(node => (node as HTMLImageElement).naturalWidth)).toBeGreaterThan(0);
    expect(videos).toEqual([]);
    for (const width of [390, 1280]) {
      await page.setViewportSize({ width, height: 844 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    expect(errors).toEqual([]);
  });

  test(`${locale}: selected video has native controls, conservative loading, real playback and stops on gallery change`, async ({ page }) => {
    const transfers: string[] = [];
    page.on('request', request => { if (/\/videos\/.*\.mp4/.test(request.url())) transfers.push(request.url()); });
    await page.goto(`/${locale}/projects/synthetic-project`);
    await page.getByRole('button', { name: locale === 'fr' ? 'Vidéo 2' : 'فيديو 2', exact: true }).click();
    const video = page.locator('video');
    await expect(video).toBeVisible();
    await expect(video).toHaveAttribute('controls', '');
    await expect(video).toHaveAttribute('preload', 'none');
    await expect(video).not.toHaveAttribute('autoplay');
    await expect(video).toHaveAttribute('poster', /\/media\/images\//);
    expect(await video.evaluate(node => (node as HTMLVideoElement).paused)).toBe(true);
    expect(transfers).toEqual([]);
    const handle = await video.elementHandle();
    await video.evaluate(node => (node as HTMLVideoElement).play());
    await expect.poll(() => video.evaluate(node => (node as HTMLVideoElement).currentTime)).toBeGreaterThan(0);
    expect(transfers.length).toBeGreaterThan(0);
    await page.getByRole('button', { name: locale === 'fr' ? 'Photo 1' : 'صورة 1', exact: true }).click();
    expect(await handle!.evaluate(node => (node as HTMLVideoElement).paused)).toBe(true);
    await expect(page.locator('video')).toHaveCount(0);
    await page.goto(`/${locale}/products/temporary-product`);
    await expect(page.getByRole('heading', { name: locale === 'fr' ? 'Projets utilisant ce matériau' : 'مشاريع تستخدم هذه المادة' })).toBeVisible();
    await expect(page.locator('[data-project-cards] article')).toHaveCount(1);
  });

  test(`${locale}: neutral cover and optional poster, draft exclusion`, async ({ page }) => {
    await page.goto(`/${locale}/projects/synthetic-no-cover`);
    await expect(page.locator('.project-gallery .product-image [data-image-placeholder]')).toBeVisible();
    await page.getByRole('button', { name: locale === 'fr' ? 'Vidéo 1' : 'فيديو 1', exact: true }).click();
    await expect(page.locator('video')).not.toHaveAttribute('poster');
    expect((await page.goto(`/${locale}/projects/synthetic-draft`))?.status()).toBe(404);
  });
}
