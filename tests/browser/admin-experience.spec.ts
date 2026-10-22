import { test, expect, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const api = process.env.QA_API_URL || 'http://127.0.0.1:8102';
const php = process.env.QA_PHP_BINARY || 'php';
const root = resolve(process.env.LARAVEL_STORAGE_PATH || 'apps/api/storage', 'framework/testing/m3-browser');
const output = resolve('.tools/m85/screenshots');
mkdirSync(output, { recursive: true });
const state = () => JSON.parse(execFileSync(php, ['apps/api/tests/Support/admin-browser.php', 'state', '--env=testing'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }));
test.beforeAll(() => {
  if (process.env.QA_M85_PRESERVE_ACCOUNT !== 'true') execFileSync(php, ['apps/api/tests/Support/admin-browser.php', 'prepare', '--env=testing'], { stdio: 'pipe' });
});
const modal = (page: Page) => page.locator('.fi-modal-window:visible').last();
async function login(page: Page, manager = false, changed = false) {
  await page.goto(`${api}/admin/login`);
  await page.locator('input[type=email]').fill(changed ? 'm85-changed@example.invalid' : `m85-${manager ? 'manager' : 'admin'}@example.invalid`);
  await page.locator('input[type=password]').fill(changed ? 'SyntheticChanged-M85-Test2!' : 'SyntheticOnly-M85-Test1!');
  await page.locator('button[type=submit]').click();
  await expect(page).toHaveURL(`${api}/admin`, { timeout: 60000 });
}
async function language(page: Page, locale: string) {
  const control = page.locator(`.admin-language-switch button[lang=${locale}]`);
  if (await control.getAttribute('aria-current') !== 'true') {
    await control.click(); await page.waitForURL(`${api}/admin`);
  }
  await expect(page.locator('html')).toHaveAttribute('lang', locale);
  await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
}
async function editor(page: Page, path: string, key: string) {
  await page.goto(`${api}/admin/${path}`);
  await page.locator('table tbody tr').filter({ has: page.getByText(key, { exact: true }) }).getByRole('button', { name: /^(Modifier|تعديل)$/ }).click();
  await expect(modal(page)).toBeVisible({ timeout: 60000 });
  await expect(modal(page).getByRole('heading', { name: /^(Modifier|تعديل)$/ })).toBeVisible();
}
async function save(page: Page) {
  await modal(page).getByRole('button', { name: /^(Enregistrer les modifications|حفظ التغييرات)$/ }).click();
  await expect(page.locator('.fi-modal-window:visible')).toHaveCount(0, { timeout: 60000 });
}
async function upload(page: Page, matcher: RegExp, file: number | string, publish: boolean) {
  const controls = page.locator('button[wire\\:click]').filter({ visible: true });
  let action: string | null | undefined;
  await expect.poll(async () => {
    action = await controls.evaluateAll((nodes, pattern) => nodes.find(n => new RegExp(pattern).test(n.getAttribute('wire:click') || ''))?.getAttribute('wire:click'), matcher.source);
    return !!action;
  }, { timeout: 30000 }).toBe(true);
  expect(action, 'visible inline upload control').toBeTruthy();
  const index = await controls.evaluateAll((nodes, target) => nodes.findIndex(n => n.getAttribute('wire:click') === target), action);
  await controls.nth(index).click();
  const dialog = modal(page);
  const fileInput = dialog.locator('.filepond--root input[type=file]');
  await expect(fileInput).toBeAttached({ timeout: 30000 });
  const uploaded = page.waitForResponse(response => response.url().includes('/update') && !!response.request().postData()?.includes('_finishUpload'));
  await fileInput.setInputFiles(resolve(root, typeof file === 'number' ? `synthetic-m85-${file}.png` : file));
  expect((await uploaded).status()).toBe(200);
  await dialog.locator('input[type=checkbox]').nth(0).check();
  if (publish) await dialog.locator('input[type=checkbox]').nth(1).check();
  await dialog.getByRole('button', { name: /^(Importer et joindre|رفع وإرفاق)$/ }).click();
  await expect(modal(page).getByRole('heading', { name: /^(Modifier|تعديل)$/ })).toBeVisible({ timeout: 120000 });
}

test('Section cover: one saved upload updates French and Arabic and survives reload', async ({ page, request }) => {
  test.setTimeout(180000); page.setDefaultTimeout(60000);
  await login(page); await language(page, 'fr');
  await editor(page, 'site-contents', 'Accueil');
  await expect(modal(page).getByText('Une seule image pour les versions française et arabe.', { exact: false })).toBeVisible();
  await upload(page, /createOption.*sections\.[^.]+\.media_asset_id/, 3, true);
  await save(page);
  const saved = state();
  const id = saved.home.translations.fr.sections[0].media_asset_id;
  expect(saved.home.translations.ar.sections[0].media_asset_id).toBe(id);
  for (const locale of ['fr', 'ar']) {
    const site = (await (await request.get(`${api}/api/v1/site?locale=${locale}`)).json()).data;
    expect(site.pages.home.sections[0].media.id).toBe(id);
    const image = await request.get(site.pages.home.sections[0].media.variants[0].url);
    expect(image.status()).toBe(200);
    expect(image.headers()['content-type']).toBe('image/webp');
  }
  await page.reload(); await editor(page, 'site-contents', 'Accueil');
  await expect(modal(page).locator('[data-selected-image-preview] img, img[data-selected-image-preview]').filter({ visible: true }).first()).toBeVisible();
  expect(state().home.translations.fr.sections[0].media_asset_id).toBe(id);
});

test('M8.5: real FR/AR responsive administration and automated accessibility', async ({ page }) => {
  test.setTimeout(900000); page.setDefaultTimeout(60000);
  await login(page);
  const axe = readFileSync('.tools/npm-cache/_npx/0f94ee7615faf582/node_modules/axe-core/axe.min.js', 'utf8');
  const reports: unknown[] = [], errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  for (const locale of ['fr', 'ar']) {
    await language(page, locale);
    for (const width of [360, 390, 430, 768, 1024, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      for (const scenario of ['dashboard', 'product', 'project', 'media', 'website', 'contacts', 'profile']) {
        if (scenario === 'product') await editor(page, 'products', 'temporary-product');
        else if (scenario === 'project') await editor(page, 'projects', 'synthetic-project');
        else if (scenario === 'website') await editor(page, 'site-contents', locale === 'fr' ? 'Accueil' : 'الرئيسية');
        else {
          await page.goto(`${api}/admin${({ dashboard: '', media: '/media-assets', contacts: '/contact-settings', profile: '/profile' } as Record<string, string>)[scenario]}`);
          if (scenario === 'dashboard') { await expect(page.locator('.fi-wi-stats-overview-stat').first()).toBeVisible(); await page.locator('.admin-recent-list').scrollIntoViewIfNeeded(); await expect(page.locator('.admin-recent-list li').first()).toBeVisible(); await page.evaluate(() => window.scrollTo(0, 0)); }
        }
        await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), `${locale} ${width} ${scenario} page overflow`).toBe(true);
        expect(await page.locator('body').innerText()).not.toMatch(/admin\.[a-z_]+|validation\.[a-z_]+/);
        await page.screenshot({ path: resolve(output, `${scenario}-${locale}-${width}.png`), fullPage: true });
        if ([390, 1440].includes(width)) {
          await page.addScriptTag({ content: axe });
          const report = await page.evaluate(async () => {
            const result = await (window as unknown as { axe: { run: (document: Document, options: unknown) => Promise<{ violations: unknown[]; incomplete: unknown[] }> } }).axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] } });
            return { violations: result.violations, incomplete: result.incomplete };
          });
          reports.push({ locale, width, scenario, ...report });
          writeFileSync('.tools/m85/admin-accessibility.json', JSON.stringify(reports, null, 2));
        }
      }
    }
  }
  expect(errors).toEqual([]);
  expect((reports as { violations: unknown[] }[]).flatMap(report => report.violations)).toEqual([]);
});

test('M8.5: private pending uploads retain section cover, then explicit approval and replacement persist across all image slots', async ({ page, request }) => {
  test.setTimeout(1200000); page.setDefaultTimeout(120000);
  await login(page); await language(page, 'fr');
  const before = state();
  const pendingFile = execFileSync(php, ['apps/api/tests/Support/admin-browser.php', 'fresh-pending', '--env=testing'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
  await editor(page, 'site-contents', 'Accueil');
  await upload(page, /createOption.*sections\.[^.]+\.media_asset_id/, pendingFile, false);
  await expect(page.getByText('Fichier privé : approbation requise', { exact: true })).toBeVisible();
  await save(page);
  const pending = state();
  expect(pending.home.translations.fr.sections[0].media_asset_id).toBe(before.home.translations.fr.sections[0].media_asset_id);
  const pendingAsset = pending.assets.find((asset: { name: string }) => asset.name === pendingFile);
  expect(pendingAsset).toMatchObject({ approval: 'pending', processing: 'ready', revision: null, originalRetained: true, checksumMatches: true });
  await page.goto(`${api}/admin/media-assets`); await page.getByRole('searchbox').fill(pendingFile);
  const row = page.locator('table tbody tr').filter({ hasText: pendingFile });
  await row.getByRole('button', { name: 'Actions', exact: true }).click();
  await page.locator('.fi-dropdown-panel:visible').getByText('Approuver l’usage public', { exact: true }).click();
  await modal(page).getByRole('button', { name: /Confirmer/ }).click();
  await expect(page.locator('.fi-modal-window:visible')).toHaveCount(0);
  await row.getByRole('button', { name: 'Actions', exact: true }).click();
  await page.locator('.fi-dropdown-panel:visible').getByText('Publier les variantes', { exact: true }).click();
  await modal(page).locator('select').selectOption({ index: 1 });
  await modal(page).locator('button[type=submit]').click(); await expect(page.locator('.fi-modal-window:visible')).toHaveCount(0);
  expect(state().assets.find((asset: { id: string }) => asset.id === pendingAsset.id).revision).toBeTruthy();
  const slots = [
    { name: 'hero', path: 'site-contents', key: 'Accueil', control: /createOption.*data.hero_media_id/, read: (s: ReturnType<typeof state>) => s.home.data.hero_media_id },
    { name: 'secondary', path: 'site-contents', key: 'Accueil', control: /createOption.*data.secondary_media_id/, read: (s: ReturnType<typeof state>) => s.home.data.secondary_media_id },
    { name: 'section', path: 'site-contents', key: 'Accueil', control: /createOption.*sections\.[^.]+\.media_asset_id/, read: (s: ReturnType<typeof state>) => s.home.translations.fr.sections[0].media_asset_id },
    { name: 'product cover', path: 'products', key: 'temporary-product', control: /createOption.*mountedActionSchema0.cover_media_id/, read: (s: ReturnType<typeof state>) => s.product.cover },
    { name: 'product gallery', path: 'products', key: 'temporary-product', control: /createOption.*gallery\..*media_asset_id/, read: (s: ReturnType<typeof state>) => s.product.gallery[0] },
    { name: 'category cover', path: 'product-categories', key: 'temporary-child', control: /createOption.*cover_media_id/, read: (s: ReturnType<typeof state>) => s.category.cover },
    { name: 'project cover', path: 'projects', key: 'synthetic-project', control: /createOption.*mountedActionSchema0.cover_media_id/, read: (s: ReturnType<typeof state>) => s.project.cover },
    { name: 'project gallery', path: 'projects', key: 'synthetic-project', control: /createOption.*gallery\..*\.media_asset_id/, read: (s: ReturnType<typeof state>) => s.project.gallery[0].media_asset_id },
    { name: 'video poster', path: 'projects', key: 'synthetic-project', control: /createOption.*gallery\..*poster_media_id/, read: (s: ReturnType<typeof state>) => s.project.gallery[1].poster_media_id },
    { name: 'logo', path: 'site-contents', key: 'Réglages globaux', control: /createOption.*data.logo_media_id/, read: (s: ReturnType<typeof state>) => s.site.data.logo_media_id },
  ];
  const evidence = [];
  for (const slot of slots) {
    for (const file of [1, 2]) {
      console.log(`Image slot: ${slot.name}, file ${file}`);
      await editor(page, slot.path, slot.key);
      await upload(page, slot.control, file, true);
      await expect(modal(page).locator('[data-selected-image-preview]').first()).toBeVisible();
      await save(page);
      const saved = state(), asset = saved.assets.find((asset: { name: string }) => asset.name === `synthetic-m85-${file}.png`);
      expect(slot.read(saved), slot.name).toBe(asset.id);
      expect(asset).toMatchObject({ approval: 'approved', processing: 'ready', originalRetained: true, checksumMatches: true });
      await page.reload();
      expect(slot.read(state())).toBe(asset.id);
      evidence.push({ slot: slot.name, file, id: asset.id, retained: asset.originalRetained });
      writeFileSync('.tools/m85/upload-slots.json', JSON.stringify(evidence, null, 2));
    }
  }
  expect(state().assets.every((asset: { originalRetained: boolean; checksumMatches: boolean }) => asset.originalRetained && asset.checksumMatches)).toBe(true);
  expect(state().baselineOriginalsRetained).toBe(true);
  for (const locale of ['fr', 'ar']) {
    const dto = await (await request.get(`${api}/api/v1/site?locale=${locale}`)).json();
    expect(JSON.stringify(dto)).toContain(state().home.data.hero_media_id);
  }
  await page.screenshot({ path: resolve(output, 'media-replacements-fr.png'), fullPage: true });
});

test('M8.5: independent content languages, contact settings and own profile credentials persist; manager settings forbidden', async ({ page, browser }) => {
  test.setTimeout(300000); page.setDefaultTimeout(60000);
  execFileSync(php, ['apps/api/tests/Support/admin-browser.php', 'reset-accounts', '--env=testing'], { stdio: 'pipe' });
  await login(page); await language(page, 'ar'); const before = state();
  await editor(page, 'site-contents', 'الرئيسية');
  await modal(page).getByRole('tab', { name: 'العربية', exact: true }).first().click();
  await modal(page).locator('input[id$="translations.ar.title"]').first().fill('عنوان عربي محفوظ من الإدارة');
  await save(page); await page.reload();
  expect(state().home.translations.fr.title).toBe(before.home.translations.fr.title);
  expect(state().home.translations.ar.title).toBe('عنوان عربي محفوظ من الإدارة');
  await page.goto(`${api}/admin/contact-settings`);
  await page.locator('input[id="form.data.phone"]').fill('+1 202 555 0123');
  await page.locator('input[id="form.data.email"]').fill('m85-contact@example.invalid');
  await page.locator('input[id="form.data.map_url"]').fill('https://maps.google.com/?q=Morocco');
  await page.locator('input[id="form.data.whatsapp.number"]').fill('+1 202 555 0124');
  const additional = page.locator('.fi-section').filter({ has: page.getByText('جهات اتصال واتساب إضافية', { exact: true }) });
  if (!await additional.locator('input[id$=".label"]').count()) await additional.getByRole('button', { name: /إضافة/ }).click();
  await additional.locator('input[id$=".label"]').first().fill('Contact QA');
  await additional.locator('input[id$=".number"]').first().fill('+1 202 555 0125');
  const social = page.locator('.fi-section').filter({ has: page.getByText('الشبكات الاجتماعية', { exact: true }) });
  if (!await social.locator('input[id$=".label"]').count()) await social.getByRole('button', { name: /إضافة/ }).click();
  await social.locator('input[id$=".label"]').first().fill('TikTok QA');
  await social.locator('input[id$=".url"]').first().fill('https://www.tiktok.com/@synthetic-qa');
  await page.locator('button[type=submit]').filter({ hasText: /حفظ/ }).click();
  await expect(page.getByText('تم حفظ التغييرات', { exact: true })).toBeVisible();
  expect(state().site.data.whatsapp.number).toBe('12025550124');
  expect(state().site.data.additional_contacts[0].number).toBe('12025550125');
  await editor(page, 'site-contents', 'الإعدادات العامة');
  await save(page);
  expect(state().site.data.additional_contacts[0].number).toBe('12025550125');
  for (const locale of ['fr', 'ar']) {
    await page.goto(`${process.env.QA_BASE_URL || 'http://127.0.0.1:3106'}/${locale}/contact`);
    await expect(page.locator('a[href="mailto:m85-contact@example.invalid"]').filter({ visible: true }).first()).toBeVisible();
    await expect(page.locator('a[href="https://wa.me/12025550125"]').filter({ visible: true }).first()).toBeVisible();
    await expect(page.locator('a[href="https://www.tiktok.com/@synthetic-qa"]').filter({ visible: true }).first()).toBeVisible();
    expect(await page.locator('a[href^="https://wa.me/12025550124"]').count()).toBeGreaterThan(0);
    await page.screenshot({ path: resolve(output, `public-contacts-${locale}.png`), fullPage: true });
  }
  await page.goto(`${api}/admin/profile`);
  await page.locator('input[type=email]').fill('m85-changed@example.invalid');
  await page.locator('input[id="form.currentPassword"]').fill('SyntheticOnly-M85-Test1!');
  await page.locator('button[type=submit]').filter({ hasText: /حفظ/ }).click();
  await expect(page.getByText('تم الحفظ', { exact: true })).toBeVisible({ timeout: 30000 });
  await page.reload();
  await expect(page).toHaveURL(`${api}/admin/profile`);
  await expect(page.locator('input[type=email]')).toHaveValue('m85-changed@example.invalid');
  await page.locator('input[id="form.password"]').fill('SyntheticChanged-M85-Test2!');
  await page.locator('input[id="form.passwordConfirmation"]').fill('SyntheticChanged-M85-Test2!');
  await page.locator('input[id="form.currentPassword"]').fill('SyntheticOnly-M85-Test1!');
  await page.locator('button[type=submit]').filter({ hasText: /حفظ/ }).click();
  await expect(page.getByText('تم الحفظ', { exact: true })).toBeVisible({ timeout: 30000 });
  await expect(page.locator('input[id="form.password"]')).toHaveValue('');
  await page.locator('.fi-user-menu').getByRole('button').first().click();
  await page.getByRole('menuitem', { name: 'تسجيل الخروج', exact: true }).click();
  await expect(page).toHaveURL(/\/admin\/login$/);
  const renewed = await browser.newContext(); const relogin = await renewed.newPage();
  await login(relogin, false, true); await expect(relogin.locator('html')).toHaveAttribute('lang', 'ar');
  await relogin.goto(`${api}/admin/profile`); await expect(relogin.locator('input[type=email]')).toHaveValue('m85-changed@example.invalid');
  await renewed.close();
  const manager = await browser.newContext(); const restricted = await manager.newPage(); await login(restricted, true);
  await expect(restricted.locator('html')).toHaveAttribute('lang', 'fr');
  expect((await restricted.goto(`${api}/admin/contact-settings`))?.status()).toBe(403);
  await manager.close();
});


test('M8.5: approved replacements reach both public locales through real derivative delivery', async ({ request }) => {
  const saved = state(); const id = saved.assets.find((asset: { name: string }) => asset.name === 'synthetic-m85-2.png').id;
  for (const locale of ['fr', 'ar']) {
    const site = (await (await request.get(`${api}/api/v1/site?locale=${locale}`)).json()).data;
    expect(site.pages.home.hero.id).toBe(id); expect(site.pages.home.secondary.id).toBe(id); expect(site.settings.logo.id).toBe(id);
    expect(site.pages.home.sections[0].media.id).toBe(id);
    const product = (await (await request.get(`${api}/api/v1/products/temporary-product?locale=${locale}`)).json()).data;
    expect(product.cover.id).toBe(id); expect(product.gallery[0].id).toBe(id);
    const categories = (await (await request.get(`${api}/api/v1/product-categories?locale=${locale}`)).json()).data;
    expect(categories.flatMap((parent: { children: unknown[] }) => parent.children).find((category: { id: string }) => category.id === saved.category.id).cover.id).toBe(id);
    const project = (await (await request.get(`${api}/api/v1/projects/synthetic-project?locale=${locale}`)).json()).data;
    expect(project.cover.id).toBe(id); expect(project.gallery[0].media.id).toBe(id); expect(project.gallery[1].poster.id).toBe(id);
    for (const image of [site.pages.home.hero, site.settings.logo, product.cover, project.gallery[1].poster]) {
      const response = await request.get(image.variants[0].url); expect(response.status()).toBe(200); expect(response.headers()['content-type']).toMatch(/^image\//);
    }
  }
  expect(saved.baselineOriginalsRetained).toBe(true);
});

test('M8.5: keyboard modal focus, visible save controls and dark-mode administration', async ({ page }) => {
  test.setTimeout(240000); page.setDefaultTimeout(60000);
  await login(page, false, true);
  const axe = readFileSync('.tools/npm-cache/_npx/0f94ee7615faf582/node_modules/axe-core/axe.min.js', 'utf8');
  const reports = [];
  for (const locale of ['fr', 'ar']) {
    await language(page, locale);
    await page.goto(`${api}/admin/products`);
    const edit = page.locator('table tbody tr').filter({ has: page.getByText('temporary-product', { exact: true }) }).getByRole('button', { name: /^(Modifier|تعديل)$/ });
    await edit.focus(); await page.keyboard.press('Enter');
    await expect(modal(page).getByRole('heading', { name: /^(Modifier|تعديل)$/ })).toBeVisible({ timeout: 60000 });
    const save = modal(page).getByRole('button', { name: /^(Enregistrer les modifications|حفظ التغييرات)$/ });
    await expect(save).toBeVisible();
    const box = await save.boundingBox(); expect(box!.y).toBeGreaterThanOrEqual(0); expect(box!.y + box!.height).toBeLessThanOrEqual(1000);
    await page.keyboard.press('Escape'); await expect(page.locator('.fi-modal-window:visible')).toHaveCount(0);
    await expect(edit).toBeFocused();
    await page.locator('.fi-user-menu').getByRole('button').first().click();
    await page.getByRole('menuitemradio', { name: locale === 'fr' ? 'Activer le mode sombre' : 'تفعيل الوضع الليلي', exact: true }).click();
    await page.keyboard.press('Escape'); await expect(page.locator('html')).toHaveClass(/dark/);
    for (const route of ['media-assets', 'profile']) {
      await page.goto(`${api}/admin/${route}`);
      await expect(page.locator('html')).toHaveClass(/dark/);
      await page.addScriptTag({ content: axe });
      const violations = await page.evaluate(async () => (await (window as unknown as { axe: { run: (document: Document, options: unknown) => Promise<{ violations: unknown[] }> } }).axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] } })).violations);
      reports.push({ locale, route, violations }); writeFileSync('.tools/m85/admin-keyboard-accessibility.json', JSON.stringify(reports, null, 2));
      await page.screenshot({ path: resolve(output, `${route}-dark-${locale}.png`), fullPage: true });
    }
    await page.locator('.fi-user-menu').getByRole('button').first().click();
    await page.getByRole('menuitemradio', { name: locale === 'fr' ? 'Désactiver le mode sombre' : 'تفعيل الوضع النهاري', exact: true }).click(); await page.keyboard.press('Escape');
  }
  expect(reports.flatMap(report => report.violations)).toEqual([]);
});
