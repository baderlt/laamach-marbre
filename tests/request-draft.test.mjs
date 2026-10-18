import test from 'node:test';
import assert from 'node:assert/strict';
import { validateMaterialDraft } from '../apps/web/src/lib/request-draft.ts';

const product = { sizes: [{ id: 'size-1', label: '4' }], finishes: [{ id: 'finish-1' }], quantity_units: ['piece'], custom_size_allowed: true, custom_size: { min_width: '5', max_width: '20', min_length: '10', max_length: '50', measurement_unit: 'mm' } };
const fields = { size: 'size-1', custom: false, width: '', length: '', finish: 'finish-1', quantity: '1', unit: 'piece', city: '', dimensions: '', description: '' };
test('quotation drafts validate authoritative selections, quantities and units', () => {
  assert.deepEqual(validateMaterialDraft(product, fields), []);
  assert.deepEqual(validateMaterialDraft(product, { ...fields, size: 'invented', finish: 'invented', quantity: '-1', unit: 'cm' }), ['size', 'finish', 'quantity', 'unit']);
  for (const quantity of ['', 'NaN', 'Infinity', '0', '  ']) assert.ok(validateMaterialDraft(product, { ...fields, quantity }).includes('quantity'));
});
test('custom dimensions require permission and use the product bounds without assuming centimetres', () => {
  const custom = { ...fields, custom: true, width: '5', length: '50' };
  assert.deepEqual(validateMaterialDraft(product, custom), []);
  assert.deepEqual(validateMaterialDraft(product, { ...custom, width: '4', length: '51' }), ['width', 'length']);
  assert.deepEqual(validateMaterialDraft({ ...product, custom_size_allowed: false }, custom), ['custom']);
  assert.deepEqual(validateMaterialDraft({ ...product, custom_size: null }, custom), ['custom']);
});
test('pending client records do not fabricate units or require invented specifications', () => {
  assert.deepEqual(validateMaterialDraft({ ...product, sizes: [], finishes: [], quantity_units: [], custom_size_allowed: false, custom_size: null }, { ...fields, size: '', finish: '', quantity: '', unit: '' }, true), []);
  assert.ok(validateMaterialDraft(product, { ...fields, description: 'x'.repeat(4001) }).includes('description'));
});
