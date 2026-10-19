import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { siteCopy, phoneHref, editorialHref } from '../apps/web/src/lib/site.ts';
import { catalogueFilters } from '../apps/web/src/lib/catalogue.ts';

test('website text respects configured fallback without adding company facts', () => {
  assert.equal(siteCopy('fr').contact, 'Contact');
  assert.equal(siteCopy('ar').contact, 'اتصل بنا');
  assert.equal(siteCopy('es').products, siteCopy('fr').products);
  assert.match(siteCopy('fr').missingMedia, /validation/);
});
test('contact phone and search URLs are bounded', () => {
  assert.equal(phoneHref('+212 (600) 000-000'), 'tel:+212600000000');
  assert.equal(phoneHref('javascript:alert(1)'), undefined);
  assert.equal(catalogueFilters({ q: ' حجر ', sort: 'name' }).get('q'), 'حجر');
  assert.equal(catalogueFilters({ q: 'x'.repeat(121), sort: 'bestselling' }).toString(), '');
});

test('editorial destinations remain local, localized and limited to supported category routes', () => {
  for (const locale of ['fr', 'ar']) {
    assert.equal(editorialHref(locale, 'home'), `/${locale}`);
    assert.equal(editorialHref(locale, 'products'), `/${locale}/products`);
    assert.equal(editorialHref(locale, 'products?category=pierre'), `/${locale}/products?category=pierre`);
    assert.equal(editorialHref(locale, 'products?parent=matiere'), `/${locale}/products?parent=matiere`);
    assert.equal(editorialHref(locale, 'projects?category=facades'), `/${locale}/projects?category=facades`);
    for (const value of ['javascript:alert(1)', '//example.com', 'https://example.com', '../admin', 'projects?parent=test', 'products?category=x&url=evil', `products?category=${'x'.repeat(121)}`]) assert.equal(editorialHref(locale, value), null);
  }
  assert.equal(editorialHref('invalid', 'products'), null);
});
test('reviewed real client mappings are checksum-pinned and contain no inferred business facts', () => {
  const mappings = JSON.parse(readFileSync(new URL('../config/client-product-assets.json', import.meta.url), 'utf8'));
  assert.equal(mappings.length, 7);
  assert.deepEqual([...new Set(mappings.map(item => item.target_key))].sort(), ['hajar-jbal-taza', 'noir-azilal', 'taza-grey']);
  for (const item of mappings) {
    assert.equal(item.client_supplied, true); assert.match(item.sha256, /^[a-f0-9]{64}$/);
    assert.equal(item.source.includes('_review'), false);
    assert.equal(Object.keys(item).some(key => ['category', 'unit', 'width', 'finish', 'price'].includes(key)), false);
  }
});
