import { test, expect } from '@playwright/test';
const api = process.env.QA_API_URL || 'http://127.0.0.1:8101';

for (const locale of ['fr', 'ar']) {
  test(`${locale}: final product copy, real categories, direct chat and sticky navigation`, async ({ page, request }) => {
    const categories = await (await request.get(`${api}/api/v1/project-categories?locale=${locale}`)).json();
    expect(categories.data).toHaveLength(5);
    expect(categories.data.some((item: { slug: string }) => item.slug.startsWith('exemple'))).toBe(false);
    for (const route of ['', '/products', '/products/grey-bnikhiran', '/projects', '/projects/pierre-pose-traditionnelle', '/services', '/about', '/contact']) {
      await page.goto(`/${locale}${route}`);
      await page.evaluate(() => window.scrollTo(0, 600));
      await expect(page.locator('.site-header')).toHaveCSS('position', 'sticky');
      expect(Math.abs((await page.locator('.site-header').boundingBox())!.y)).toBeLessThan(2);
    }
    for (const [kind, route, title] of [['material', '/products/grey-bnikhiran', locale === 'ar' ? 'رمادي بني خيران' : 'Grey Bnikhiran'], ['similar', '/projects/pierre-pose-traditionnelle', locale === 'ar' ? 'الحجر بالطريقة التقليدية' : 'Pierre : pose traditionnelle']]) {
      await page.goto(`/${locale}${route}`);
      const chat = page.locator('a.direct-whatsapp').first();
      await expect(chat).toBeVisible();
      const link = new URL((await chat.getAttribute('href'))!);
      expect(link.hostname).toBe('wa.me');
      expect(link.pathname).toBe('/212706795408');
      expect(link.searchParams.get('text')).toContain(title);
      expect(link.searchParams.get('text')).toContain(`/${locale}${route}`);
      if (kind === 'material') {
        await expect(page.locator('.technical-section .facts-table > div')).toHaveCount(7);
        await expect(page.locator('.product-description')).toContainText('150');
        expect(await page.locator('.product-related-projects .project-card').count()).toBeGreaterThan(0);
        await expect(page.locator('body')).not.toContainText(/provisoire|قيد المراجعة|صور مؤقتة/);
      } else expect(await page.locator('[data-materials-used] .product-card').count()).toBeGreaterThan(0);
      for (const width of [390, 768, 1242, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      }
    }
  });
}
