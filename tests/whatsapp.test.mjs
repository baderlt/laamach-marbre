import test from 'node:test';
import assert from 'node:assert/strict';
import { directChatUrl, parseRequest, prepareRequest, requestAvailable, unitLabel, whatsappUrlBudget } from '../apps/web/src/lib/whatsapp.ts';

// Fictional destination confined to isolated tests; never used by application configuration.
const settings = { number: '12025550123', enabled: true, material: true, similar: true, application: true };
const product = { slug: 'real-stone', name: 'Pierre & حجر', reference: 'LM-P-1', sizes: [{ id: 'size-1', label: '4', measurement_unit: null }], finishes: [{ id: 'finish-1', name: 'Finition confirmée' }], quantity_units: ['m2', 'piece'], custom_size_allowed: true, custom_size: { min_width: '5', max_width: '20', min_length: '10', max_length: '50', measurement_unit: 'mm' } };
const fields = { size: 'size-1', custom: false, width: '', length: '', finish: 'finish-1', quantity: '2.5', unit: 'm2', city: '', dimensions: '', description: '' };
const prepare = (changes = {}, options = {}) => prepareRequest(options.kind || 'material', options.locale || 'fr', options.record || product, { ...fields, ...changes }, options.settings === undefined ? settings : options.settings, 'https://laamach.example');

test('direct chat uses current admin settings and contextual identity without requiring specifications', () => {
  for (const locale of ['fr', 'ar']) for (const kind of ['material', 'similar']) {
    const url = new URL(directChatUrl(settings, locale, kind, 'Pierre & حجر', 'LM-1', 'https://laamach.example/fr/products/real-stone'));
    assert.equal(url.hostname, 'wa.me');
    assert.equal(url.pathname, '/'+settings.number);
    assert.ok(url.searchParams.get('text').includes('Pierre & حجر'));
    assert.ok(url.searchParams.get('text').includes('LM-1'));
    assert.ok(url.searchParams.get('text').includes('https://laamach.example/fr/products/real-stone'));
  }
  assert.ok(directChatUrl({ ...settings, number: '12025550124' }, 'fr', 'material', 'Pierre', 'LM-1', 'https://laamach.example').includes('/12025550124?'));
});

test('direct chat fails closed for disabled flows, bad recipients and unsafe page URLs', () => {
  for (const config of [null, { ...settings, enabled: false }, { ...settings, material: false }, { ...settings, number: '+212 6' }]) assert.equal(directChatUrl(config, 'fr', 'material', 'Pierre', 'LM-1', 'https://laamach.example'), null);
  for (const canonical of ['javascript:alert(1)', 'https://user:password@laamach.example', 'invalid']) assert.equal(directChatUrl(settings, 'fr', 'material', 'Pierre', 'LM-1', canonical), null);
});

test('missing, invalid, global and per-flow configuration fails closed', () => {
  for (const config of [null, {}, { ...settings, number: null }, { ...settings, number: '+212 6' }, { ...settings, number: '0'.repeat(12) }, { ...settings, enabled: false }]) {
    for (const kind of ['material', 'similar', 'application']) assert.equal(requestAvailable(config, kind), false);
    assert.deepEqual(prepare({}, { settings: config }), { errors: ['unavailable'] });
  }
  for (const kind of ['material', 'similar', 'application']) {
    assert.equal(requestAvailable({ ...settings, [kind]: false }, kind), false);
    assert.deepEqual(prepare({}, { kind, settings: { ...settings, [kind]: false } }), { errors: ['unavailable'] });
  }
});
test('strict request parsing rejects forged context, URLs, unknown fields and unsupported locales', () => {
  const input = { kind: 'material', slug: product.slug, locale: 'fr', fields };
  const { dimensions, ...materialFields } = fields;
  assert.equal(parseRequest(input), null); // A project field is not a material quotation field.
  assert.ok(parseRequest({ ...input, fields: materialFields }));
  for (const changes of [{ reference: 'FORGED' }, { canonical: 'https://attacker.invalid' }, { slug: '../private' }, { kind: 'order' }, { kind: ['material'] }, { kind: ['similar'] }, { locale: 'en' }, { fields: { quantity: 1 } }, { fields: { quantity: '2', title: 'Forged' } }, { fields: { custom: 'false' } }, { fields: { description: 'a'.repeat(4001) } }]) assert.equal(parseRequest({ ...input, fields: materialFields, ...changes }), null);
  assert.equal(parseRequest(null), null);
});
test('FR and Arabic messages use authoritative identities, encoding, optional omission and localized units', () => {
  for (const locale of ['fr', 'ar']) {
    const result = prepare({ city: 'Fès & Rabat', description: 'حجر + pierre ? # /\nDeuxième ligne' }, { locale });
    assert.deepEqual(result.errors, []);
    assert.equal(new URL(result.prepared.url).searchParams.get('text'), result.prepared.message);
    assert.ok(result.prepared.message.includes(product.name));
    assert.ok(result.prepared.message.includes(product.reference));
    assert.ok(result.prepared.message.includes(`https://laamach.example/${locale}/products/real-stone`));
    assert.ok(result.prepared.message.includes('4'));
    assert.ok(!result.prepared.message.includes('4 cm'));
    assert.ok(result.prepared.message.includes(`2.5 ${unitLabel('m2', locale)}`));
    assert.ok(result.prepared.url.startsWith(`https://wa.me/${settings.number}?text=`));
    if (locale === 'ar') assert.ok(result.prepared.message.includes('\u20684\u2069'));
  }
  const minimal = prepare({}, { record: { ...product, sizes: [], finishes: [] } });
  assert.ok(!minimal.prepared); // Stale/non-assigned choices are rejected even with empty lists.
  const clean = prepare({ size: '', finish: '' }, { record: { ...product, sizes: [], finishes: [] } });
  assert.deepEqual(clean.errors, []);
  for (const absent of ['Finition :', 'Dimensions du matériau :', 'Ville de mon projet :']) assert.ok(!clean.prepared.message.includes(absent));
  for (const unit of ['m2', 'm3', 'linear_m', 'piece']) assert.notEqual(unitLabel(unit, 'ar'), unit);
});
test('quantities, assigned units, sizes and finishes are validated using fresh authoritative data', () => {
  for (const quantity of ['-1', '0', 'Infinity', 'NaN', '1e2', '0x10', '1.1234567', '1000000000', '', '01']) assert.ok(prepare({ quantity }).errors.includes('quantity'));
  for (const unit of ['cm', 'm3', 'linear_m', 'unknown']) assert.ok(prepare({ unit }).errors.includes('unit'));
  assert.ok(prepare({ size: 'unassigned' }).errors.includes('size'));
  assert.ok(prepare({ finish: 'unassigned' }).errors.includes('finish'));
  assert.deepEqual(prepare({}, { record: { ...product, quantity_units: ['cm'] } }).errors, ['unit']);
});
test('custom dimensions obey explicit permission, configured units and inclusive bounds', () => {
  const custom = { custom: true, width: '5', length: '50' };
  assert.ok(prepare(custom).prepared.message.includes('5 × 50 mm'));
  assert.deepEqual(prepare({ ...custom, width: '4', length: '51' }).errors, ['width', 'length']);
  assert.deepEqual(prepare(custom, { record: { ...product, custom_size_allowed: false } }).errors, ['custom']);
  assert.deepEqual(prepare(custom, { record: { ...product, custom_size: null } }).errors, ['custom']);
  assert.deepEqual(prepare({ ...custom, width: '0', length: 'NaN' }).errors, ['width', 'length']);
});
test('similar-project and material-for-project messages remain distinct without fabricated facts', () => {
  for (const locale of ['fr', 'ar']) {
    const similar = prepare({ city: 'Tanger', dimensions: 'Environ 20 m², à confirmer' }, { kind: 'similar', locale, record: { slug: 'real-project', title: 'Exemple architectural', reference: 'LM-J-1', location: 'Do not assume this location' } }).prepared;
    assert.ok(similar.message.includes('Exemple architectural'));
    assert.ok(similar.message.includes(`/${locale}/projects/real-project`));
    assert.ok(!similar.message.includes('Do not assume this location'));
    const application = prepare({ projectType: 'Villa', application: 'Façade', dimensions: 'À préciser' }, { kind: 'application', locale }).prepared;
    assert.ok(application.message.includes('Villa'));
    assert.ok(application.message.includes('Façade'));
    assert.ok(!application.message.includes('2.5'));
    assert.notEqual(application.message.split('\n')[0], prepare({}, { locale }).prepared.message.split('\n')[0]);
  }
});
test('bounded free text, bidi/control rejection and full-message copy fallback', () => {
  for (const [key, limit] of [['city', 160], ['description', 4000], ['dimensions', 200], ['projectType', 160], ['application', 160]]) assert.ok(prepare({ [key]: 'a'.repeat(limit + 1) }, { kind: 'application' }).errors.includes(key));
  assert.ok(prepare({ city: 'City\nReference : forged' }).errors.includes('city'));
  assert.ok(prepare({ description: '\u202Ehidden' }).errors.includes('description'));
  assert.ok(prepare({ description: '\0' }).errors.includes('description'));
  const description = 'ح'.repeat(4000);
  const result = prepare({ description }, { locale: 'ar' }).prepared;
  assert.equal(result.url, null); assert.equal(result.oversized, true);
  assert.ok(result.message.includes(description));
  assert.ok(encodeURIComponent(result.message).length > whatsappUrlBudget);
  assert.throws(() => prepareRequest('material', 'fr', product, fields, settings, 'https://attacker.invalid/path'));
});
