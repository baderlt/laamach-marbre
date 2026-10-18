import { test, expect } from '@playwright/test';

for (const locale of ['fr', 'ar']) {
  test(`${locale}: local material request validates custom bounds and restores keyboard focus`, async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const sent: string[] = [];
    page.on('request', request => { if (request.method() !== 'GET' || /wa\.me|whatsapp|\.glb|\.gltf/i.test(request.url())) sent.push(request.url()); });
    await page.goto(`/${locale}/products/temporary-product`);
    const trigger = page.locator('.request-material > button');
    await trigger.click(); const dialog = page.locator('.request-material dialog');
    await expect(dialog).toBeVisible(); await expect(dialog).toHaveAttribute('aria-labelledby');
    await dialog.locator('button[type=submit]').click();
    await expect(dialog.locator('[role=alert]')).toBeFocused();
    await expect(dialog.locator('[name=size]')).toHaveAttribute('aria-invalid', 'true');
    await dialog.locator('input[type=checkbox]').check();
    await dialog.locator('[name=width]').fill('4'); await dialog.locator('[name=length]').fill('61');
    await dialog.locator('[name=quantity]').fill('2');
    await dialog.locator('button[type=submit]').click();
    await expect(dialog.locator('[name=width]')).toHaveAttribute('aria-invalid', 'true');
    await expect(dialog.locator('[name=length]')).toHaveAttribute('aria-invalid', 'true');
    await dialog.locator('[name=width]').fill('5'); await dialog.locator('[name=length]').fill('60');
    await dialog.locator('button[type=submit]').click();
    await expect(dialog.locator('.request-review')).toBeVisible();
    await expect(dialog.locator('.request-review')).toContainText('5 × 60 cm');
    await page.screenshot({ path: `.tools/m52/${locale}-material-request.png`, fullPage: true });
    await page.keyboard.press('Escape'); await expect(dialog).not.toBeVisible(); await expect(trigger).toBeFocused();
    await page.keyboard.press('Enter'); await expect(dialog).toBeVisible();
    for (let index = 0; index < 12; index++) {
      await page.keyboard.press(index % 2 ? 'Shift+Tab' : 'Tab');
      expect(await dialog.evaluate(node => node.contains(document.activeElement))).toBe(true);
    }
    await page.keyboard.press('Escape');
    expect(sent).toEqual([]);
  });

  test(`${locale}: similar-project and material-for-project journeys remain distinct and local`, async ({ page }) => {
    await page.goto(`/${locale}/projects/synthetic-project`);
    const trigger = page.locator('.request-similar > button'); await trigger.click();
    const dialog = page.locator('.request-similar dialog');
    await expect(dialog.locator('.request-identity')).toContainText('Synthetic project');
    await dialog.locator('[name=city]').fill('Test city');
    await dialog.locator('[name=dimensions]').fill('Dimensions à confirmer');
    await dialog.locator('[name=description]').fill('Test request, not a real client enquiry.');
    await dialog.locator('button[type=submit]').click(); await expect(dialog.locator('.request-review')).toContainText('Test city');
    await page.screenshot({ path: `.tools/m52/${locale}-similar-project.png`, fullPage: true });
    await page.keyboard.press('Escape'); await expect(trigger).toBeFocused();
    await page.goto(`/${locale}/products/temporary-product`); await page.locator('.request-application > button').click();
    const application = page.locator('.request-application dialog');
    await expect(application.locator('[name=city]')).toBeVisible();
    await expect(application.locator('[name=quantity]')).toHaveCount(0);
    await expect(application.locator('.request-identity')).toContainText('Temporary product');
    await page.keyboard.press('Escape');
  });
}
