import test from 'node:test';
import assert from 'node:assert/strict';
import { contactChatUrl } from '../apps/web/src/lib/contact.ts';

const settings = { enabled: true, number: '212706795408', material: true, similar: true, application: true };
const fields = { name: 'Amine', email: '', phone: '+212600000000', request: 'Devis', message: 'Une façade en pierre naturelle.\nDimensions à discuter.' };

test('contact links use the current admin recipient and honor global availability', () => {
  assert.equal(new URL(contactChatUrl(settings, 'fr').url).pathname, '/212706795408');
  assert.equal(new URL(contactChatUrl({ ...settings, number: '212600000001' }, 'fr').url).pathname, '/212600000001');
  for (const entry of [null, { ...settings, enabled: false }, { ...settings, number: 'javascript:alert(1)' }, { ...settings, number: '' }]) assert.equal(contactChatUrl(entry, 'fr'), null);
  assert.equal(contactChatUrl(settings, 'unsupported'), null);
});

test('FR and Arabic enquiries are correctly encoded and omit empty optional fields', () => {
  for (const locale of ['fr', 'ar']) {
    const result = contactChatUrl(settings, locale, { ...fields, name: locale === 'ar' ? 'أمين' : 'Amine' });
    assert.ok(result);
    assert.equal(new URL(result.url).searchParams.get('text'), result.message);
    assert.ok(result.message.includes(fields.message));
    assert.ok(!result.message.includes('E-mail :'));
    assert.ok(!result.message.includes('البريد الإلكتروني :'));
  }
});

test('contact fields reject empty required content, bad emails, controls and excessive lengths', () => {
  for (const change of [{ name: ' ' }, { request: '' }, { message: '' }, { email: 'not-an-email' }, { name: 'x'.repeat(121) }, { message: '\u202econcealed' }, { phone: 'x'.repeat(41) }]) assert.equal(contactChatUrl(settings, 'fr', { ...fields, ...change }), null);
  assert.equal(contactChatUrl(settings, 'ar', { ...fields, message: 'ح'.repeat(1800) }), null);
});
