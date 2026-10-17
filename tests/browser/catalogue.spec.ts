import { test, expect } from '@playwright/test';

for (const locale of ['fr', 'ar']) {
  test(`${locale}: catalogue filters, responsive detail and size media switching`, async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/${locale}/products`);
    await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
    await expect(page.locator('.product-grid article')).toHaveCount(2);
    await page.locator('select[name=parent]').selectOption('temporary-parent');
    await page.locator('form button').click();
    await expect(page).toHaveURL(/parent=temporary-parent/);
    await page.locator('select[name=category]').selectOption('temporary-child');
    await page.locator('select[name=finish]').selectOption('temporary-finish');
    await page.locator('form button').click();
    await expect(page).toHaveURL(/category=temporary-child/);
    await page.goto(`/${locale}/products/temporary-product`);
    const image = page.locator('[data-product-image] img');
    const cover = await image.getAttribute('data-media-id');
    await expect(image).toBeVisible();
    await expect.poll(() => image.evaluate(node => (node as HTMLImageElement).naturalWidth)).toBeGreaterThan(0);
    await page.getByRole('button', { name: locale === 'fr' ? 'Vue du produit 1' : 'صورة المنتج 1', exact: true }).click();
    await expect(image).not.toHaveAttribute('data-media-id', cover!);
    await page.getByRole('button', { name: locale === 'fr' ? 'Vue principale' : 'الصورة الرئيسية', exact: true }).click();
    await expect(image).toHaveAttribute('data-media-id', cover!);
    await page.getByRole('button', { name: 'Dedicated size', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Dedicated size', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await expect(image).not.toHaveAttribute('data-media-id', cover!);
    const dedicated = await image.getAttribute('data-media-id');
    await page.locator('.field-label select').selectOption({ index: 1 });
    await expect(image).toHaveAttribute('data-media-id', dedicated!);
    await page.getByRole('button', { name: 'Fallback size', exact: true }).click();
    await expect(image).toHaveAttribute('data-media-id', cover!);
    await expect(page.locator('[data-custom-size]')).toBeVisible();
    for (const width of [390, 1280]) {
      await page.setViewportSize({ width, height: 844 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    expect(errors).toEqual([]);
  });

  test(`${locale}: no-image fallback and draft exclusion`, async ({ page }) => {
    await page.goto(`/${locale}/products/temporary-no-image`);
    await expect(page.locator('[data-product-image] [data-image-placeholder]')).toBeVisible();
    await page.getByRole('button', { name: 'Fallback size', exact: true }).click();
    await expect(page.locator('[data-product-image] [data-image-placeholder]')).toBeVisible();
    await expect(page.locator('[data-custom-size]')).toHaveCount(0);
    expect((await page.goto(`/${locale}/products/temporary-draft`))?.status()).toBe(404);
  });
}
