import test from 'node:test';
import assert from 'node:assert/strict';
import { selectedImage, catalogueFilters, catalogueCopy } from '../apps/web/src/lib/catalogue.ts';

test('size image selection is deterministic with cover and neutral fallbacks', () => {
  const cover = { id: 'cover', alt: '', variants: [] };
  const dedicated = { id: 'dedicated', alt: '', variants: [] };
  const product = { cover, sizes: [{ id: 'a', cover: dedicated }, { id: 'b', cover: null }] };
  assert.equal(selectedImage(product, null), cover);
  assert.equal(selectedImage(product, 'a'), dedicated);
  assert.equal(selectedImage(product, 'b'), cover);
  assert.equal(selectedImage({ ...product, cover: null }, 'b'), null);
  assert.equal(selectedImage(product, 'unknown'), cover);
});

test('URL filters accept only bounded known catalogue filters', () => {
  assert.equal(catalogueFilters({ parent: 'parent', category: 'child', finish: 'finish', page: '2', price: '100' }).toString(), 'parent=parent&category=child&finish=finish&page=2');
  assert.equal(catalogueFilters({ parent: '../escape', category: ['a', 'b'], page: '10001' }).toString(), '');
  assert.equal(catalogueFilters({ page: '10000' }).get('page'), '10000');
});

test('catalogue messages support configured fallback and neutral custom-size copy', () => {
  assert.match(catalogueCopy('fr').custom, /faisabilité/);
  assert.match(catalogueCopy('ar').custom, /الشركة/);
  assert.equal(catalogueCopy('es').catalogue, catalogueCopy('fr').catalogue);
});
