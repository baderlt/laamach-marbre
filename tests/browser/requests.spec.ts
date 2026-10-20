import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const base = process.env.QA_BASE_URL || 'http://127.0.0.1:3101';
const api = process.env.QA_API_URL || 'http://127.0.0.1:8101';
mkdirSync('.tools/m7/screenshots', { recursive: true });

for (const locale of ['fr', 'ar']) {
  test(`${locale}: material request preserves size images, validates bounds and reviews safely`, async ({ page, context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await page.setViewportSize({ width: 390, height: 844 });
    const external: string[] = [];
    page.on('request', request => { if (/wa\.me|whatsapp|\.glb|\.gltf/i.test(request.url())) external.push(request.url()); });
    await page.goto(`/${locale}/products/temporary-product`);
    const image = page.locator('[data-product-image] img'); const cover = await image.getAttribute('src');
    const trigger = page.locator('.request-material > button'); await trigger.click();
    const dialog = page.locator('.request-material dialog');
    await expect(dialog).toBeVisible(); await expect(dialog).toHaveAttribute('aria-labelledby');
    await dialog.locator('button[type=submit]').click();
    await expect(dialog.locator('[role=alert]')).toBeFocused();
    await expect(dialog.locator('[name=size]')).toHaveAttribute('aria-invalid', 'true');
    await dialog.locator('[name=size]').selectOption({ label: 'Dedicated size' });
    expect(await image.getAttribute('src')).not.toBe(cover);
    await dialog.locator('input[type=checkbox]').check();
    await dialog.locator('[name=width]').fill('4'); await dialog.locator('[name=length]').fill('61');
    await dialog.locator('[name=quantity]').fill('-2');
    await dialog.locator('button[type=submit]').click();
    for (const field of ['width', 'length', 'quantity']) await expect(dialog.locator(`[name=${field}]`)).toHaveAttribute('aria-invalid', 'true');
    await dialog.locator('[name=width]').fill('5'); await dialog.locator('[name=length]').fill('60'); await dialog.locator('[name=quantity]').fill('2.5');
    await dialog.locator('[name=city]').fill('Fès & الرباط');
    await dialog.locator('button[type=submit]').click();
    const review = dialog.locator('.request-review'); await expect(review).toBeVisible(); await expect(review).toBeFocused();
    const message = await review.locator('textarea').inputValue();
    expect(message).toContain('5 × 60 cm'); expect(message).toContain(`2.5 ${locale === 'ar' ? 'متر مربع' : 'm²'}`);
    expect(message).toContain(`${base}/${locale}/products/temporary-product`); expect(message).toContain('Fès & الرباط');
    const link = review.locator('[data-whatsapp]'); const url = new URL((await link.getAttribute('href'))!);
    expect(url.origin).toBe('https://wa.me'); expect(url.searchParams.get('text')).toBe(message);
    await expect(link).toHaveAttribute('target', '_blank'); await expect(link).toHaveAttribute('rel', 'noopener noreferrer');
    await review.getByRole('button', { name: locale === 'ar' ? 'انسخ الرسالة' : 'Copier le message', exact: true }).click();
    expect((await page.evaluate(() => navigator.clipboard.readText())).replaceAll('\r\n', '\n')).toBe(message);
    await expect(review.locator('[role=status]')).toContainText(locale === 'ar' ? 'أرسلها بنفسك' : 'envoyez-le vous-même');
    await page.screenshot({ path: `.tools/m7/screenshots/${locale}-material-review-390.png`, fullPage: false });
    for (let index = 0; index < 16; index++) {
      await page.keyboard.press(index < 8 ? 'Tab' : 'Shift+Tab');
      expect(await dialog.evaluate(node => node.contains(document.activeElement))).toBe(true);
    }
    await page.keyboard.press('Escape'); await expect(dialog).not.toBeVisible(); await expect(trigger).toBeFocused();
    expect(external).toEqual([]);
  });

  test(`${locale}: distinct project and material-application messages, optional fields and copy fallback`, async ({ page }) => {
    await page.goto(`/${locale}/projects/synthetic-project`); await page.locator('.request-similar > button').click();
    const dialog = page.locator('.request-similar dialog');
    await expect(dialog.locator('.request-identity')).toContainText('Synthetic project');
    await dialog.locator('[name=city]').fill('Test city'); await dialog.locator('[name=dimensions]').fill('20 m² à confirmer');
    await dialog.locator('button[type=submit]').click();
    await expect(dialog.locator('.request-review')).toBeVisible();
    const similar = await dialog.locator('.request-review textarea').inputValue();
    expect(similar).toContain('Test city'); expect(similar).toContain(`/${locale}/projects/synthetic-project`);
    expect(similar).not.toContain('Synthetic location');
    await page.keyboard.press('Escape');
    await page.goto(`/${locale}/products/temporary-product`); const trigger = page.locator('.request-application > button'); await trigger.click();
    const application = page.locator('.request-application dialog');
    await expect(application.locator('[name=quantity]')).toHaveCount(0);
    await application.locator('[name=projectType]').fill('Villa'); await application.locator('[name=application]').fill('Façade');
    await application.locator('button[type=submit]').click();
    const output = application.locator('.request-review textarea'); await expect(output).toHaveValue(/Villa/);
    expect(await output.inputValue()).toContain('Façade'); expect(await output.inputValue()).not.toBe(similar);
    await page.evaluate(() => { Object.defineProperty(navigator.clipboard, 'writeText', { configurable: true, value: () => Promise.reject(new Error('Unavailable in isolated test')) }); });
    await application.getByRole('button', { name: locale === 'ar' ? 'انسخ الرسالة' : 'Copier le message', exact: true }).click();
    await expect(output).toBeFocused();
    expect(await output.evaluate(node => node.selectionEnd - node.selectionStart)).toBe((await output.inputValue()).length);
    await page.keyboard.press('Escape'); await expect(trigger).toBeFocused();
  });

  test(`${locale}: 360–1440 request layouts, labels, touch targets, keyboard and RTL`, async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    for (const width of [360, 390, 430, 768, 1440]) {
      await page.setViewportSize({ width, height: 844 });
      for (const kind of ['material', 'application', 'similar']) {
        await page.goto(`/${locale}/${kind === 'similar' ? 'projects/synthetic-project' : 'products/temporary-product'}`);
        const trigger = page.locator(`.request-${kind} > button`); await trigger.click(); const dialog = page.locator(`.request-${kind} dialog`);
        await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
        const bounds = await dialog.boundingBox(); expect(bounds!.x).toBeGreaterThanOrEqual(0); expect(bounds!.width).toBeLessThanOrEqual(width);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        expect(await dialog.evaluate(node => node.scrollWidth <= node.clientWidth + 1)).toBe(true);
        const labels = await dialog.locator('input, select, textarea').evaluateAll(nodes => nodes.filter(node => !(node as HTMLInputElement).labels?.length).length); expect(labels).toBe(0);
        for (const node of await dialog.locator('input:not([type=checkbox]),select,textarea,button').all()) {
          if (!(await node.isVisible())) continue;
          const box = await node.boundingBox(); expect(box!.height).toBeGreaterThanOrEqual(44);
        }
        await page.screenshot({ path: `.tools/m7/screenshots/${locale}-${kind}-${width}.png`, fullPage: false });
        const session = await page.context().newCDPSession(page); const tree = await session.send('Accessibility.getFullAXTree');
        expect(tree.nodes.some(node => node.role?.value === 'dialog' && !!node.name?.value)).toBe(true); await session.detach();
        await page.keyboard.press('Tab'); expect(await dialog.evaluate(node => node.contains(document.activeElement))).toBe(true);
        await page.keyboard.press('Escape'); await expect(trigger).toBeFocused();
      }
    }
  });
}

test('preparation endpoint rejects forged/unpublished context, origin misuse and oversized payloads', async ({ request, page }) => {
  const product = (await (await request.get(`${api}/api/v1/products/temporary-product?locale=fr`)).json()).data;
  const input = { kind: 'material', slug: product.slug, locale: 'fr', fields: { size: product.sizes[0].id, finish: product.finishes[0].id, quantity: '2', unit: 'm2' } };
  const post = (data: unknown, origin = base) => request.post('/api/requests', { headers: { Origin: origin }, data });
  expect((await post(input, 'https://attacker.invalid')).status()).toBe(403);
  expect((await post({ ...input, reference: 'FORGED' })).status()).toBe(422);
  expect((await post({ ...input, kind: ['material'] })).status()).toBe(422);
  expect((await post({ ...input, canonical: 'https://attacker.invalid' })).status()).toBe(422);
  for (const fields of [{ ...input.fields, size: 'unassigned' }, { ...input.fields, finish: 'unassigned' }, { ...input.fields, quantity: '-1' }, { ...input.fields, unit: 'm3' }, { ...input.fields, custom: true, width: '41', length: '9' }]) expect((await post({ ...input, fields })).status()).toBe(422);
  expect((await post({ ...input, slug: 'temporary-draft' })).status()).toBe(404);
  expect((await post({ kind: 'similar', slug: 'synthetic-draft', locale: 'ar', fields: {} })).status()).toBe(404);
  expect((await post({ ...input, fields: { ...input.fields, description: 'a'.repeat(17000) } })).status()).toBe(413);
  const untrusted = await request.post('/api/requests', { headers: { Origin: base, Host: 'attacker.invalid' }, data: input });
  expect(untrusted.status()).toBe(400); expect(await untrusted.text()).not.toContain('prepared');
  const response = await post(input); expect(response.status()).toBe(200); expect(response.headers()['cache-control']).toBe('no-store');
  const data = await response.json(); expect(data.prepared.canonical).toBe(`${base}/fr/products/temporary-product`);
  expect(JSON.stringify(data)).not.toMatch(/original_key|originals\//);
  const noFinish = (await (await request.get(`${api}/api/v1/products/temporary-no-image?locale=fr`)).json()).data;
  expect(noFinish.finishes).toEqual([]);
  await page.goto('/fr/products/temporary-no-image'); await page.locator('.request-material > button').click();
  await expect(page.locator('.request-material dialog [name=finish]')).toHaveCount(0);
  expect((await post({ ...input, slug: noFinish.slug, fields: { ...input.fields, size: noFinish.sizes[0].id, finish: '' } })).status()).toBe(200);
  expect((await post({ ...input, slug: noFinish.slug, fields: { ...input.fields, custom: true, width: '5', length: '20' } })).status()).toBe(422);
});

test('oversized Arabic message retains all text and offers copy instead of a long WhatsApp link', async ({ page }) => {
  await page.goto('/ar/projects/synthetic-project'); await page.locator('.request-similar > button').click();
  const dialog = page.locator('.request-similar dialog'); const text = 'ح'.repeat(4000);
  await dialog.locator('[name=description]').fill(text); await dialog.locator('button[type=submit]').click();
  await expect(dialog.locator('.request-review')).toBeVisible();
  expect(await dialog.locator('.request-review textarea').inputValue()).toContain(text);
  await expect(dialog.locator('[data-whatsapp]')).toHaveCount(0);
  await expect(dialog.locator('.request-contact a')).toHaveCount(1);
});

test('a configuration change or failed preparation presents an honest contact fallback', async ({ page }) => {
  await page.goto('/fr/projects/synthetic-project'); await page.locator('.request-similar > button').click();
  await page.route('**/api/requests', route => route.fulfill({ status: 409, contentType: 'application/json', body: JSON.stringify({ errors: ['unavailable'] }) }));
  const dialog = page.locator('.request-similar dialog'); await dialog.locator('button[type=submit]').click();
  await expect(dialog.locator('[role=alert]')).toBeFocused(); await expect(dialog.locator('[role=alert] a')).toHaveAttribute('href', '/fr/contact');
  await expect(dialog.locator('[data-whatsapp]')).toHaveCount(0); await expect(dialog.locator('.request-review')).not.toBeVisible();
});
