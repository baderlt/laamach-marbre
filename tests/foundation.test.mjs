import test from 'node:test';
import assert from 'node:assert/strict';
import { localeConfiguration, validateLocales, localeForPath } from '../apps/web/src/lib/locales.ts';
import { origin, serverEnvironment } from '../apps/web/src/lib/environment.ts';
import { getMessages } from '../apps/web/src/lib/messages.ts';

test('initial enabled locales and text direction come from metadata', () => {
  assert.deepEqual(localeConfiguration.locales.map(locale => locale.code), ['fr', 'ar']);
  assert.equal(localeForPath('/ar').direction, 'rtl');
  assert.equal(localeForPath('/fr').direction, 'ltr');
  assert.equal(localeForPath('/unknown').code, localeConfiguration.default);
});

test('a new language and default require no fixed language list', () => {
  const entry = { code: 'de', name: 'Deutsch', direction: 'ltr' };
  assert.equal(validateLocales({ default: 'de', fallback: 'de', locales: [entry] }).default, 'de');
  assert.throws(() => validateLocales({ default: 'de', fallback: 'de', locales: [entry, entry] }));
  assert.throws(() => validateLocales({ default: 'missing', fallback: 'de', locales: [entry] }));
  assert.throws(() => validateLocales({ default: 'de', fallback: 'missing', locales: [entry] }));
  assert.throws(() => validateLocales({ default: 'de', fallback: 'de', locales: [{ ...entry, direction: 'invalid' }] }));
  assert.throws(() => validateLocales({ locales: [] }));
});

test('shell translation fallback uses configured fallback', () => {
  assert.equal(getMessages('not-translated').title, getMessages(localeConfiguration.fallback).title);
});

test('service origins reject credentials, paths and unsafe protocols', () => {
  assert.equal(origin('http://127.0.0.1:8100', 'backend'), 'http://127.0.0.1:8100');
  for (const value of [undefined, 'invalid', 'file:///tmp/app', 'https://user:password@example.invalid', 'https://example.invalid/path', 'https://example.invalid?secret=1', 'https://example.invalid/#fragment']) {
    assert.throws(() => origin(value, 'backend'));
  }
});

test('environment configuration requires both origins and bounded deadlines', () => {
  const environment = { SITE_URL: 'http://127.0.0.1:3100', LARAVEL_API_URL: 'http://127.0.0.1:8100' };
  assert.equal(serverEnvironment(environment).timeout, 3000);
  for (const value of ['0', '9000', 'NaN', '1.5']) assert.throws(() => serverEnvironment({ ...environment, API_TIMEOUT_MS: value }));
  assert.throws(() => serverEnvironment({ SITE_URL: environment.SITE_URL }));
});
