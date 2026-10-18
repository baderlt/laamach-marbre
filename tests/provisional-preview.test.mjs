import test from 'node:test';
import assert from 'node:assert/strict';
import { provisionalPreviewEnabled, provisionalImageLabel } from '../apps/web/src/lib/provisional-preview.ts';

test('provisional AI media requires an explicit flag and the isolated loopback preview backend', () => {
  const valid = { LAAMACH_PROVISIONAL_PREVIEW: '1', SITE_URL: 'http://127.0.0.1:3102', LARAVEL_API_URL: 'http://127.0.0.1:8101' };
  assert.equal(provisionalPreviewEnabled(valid), true);
  for (const override of [{ LAAMACH_PROVISIONAL_PREVIEW: '' }, { SITE_URL: 'https://example.com' }, { LARAVEL_API_URL: 'http://127.0.0.1:8100' }, { LARAVEL_API_URL: 'https://example.com:8101' }, { SITE_URL: 'http://user@localhost:3102' }, { SITE_URL: 'invalid' }]) assert.equal(provisionalPreviewEnabled({ ...valid, ...override }), false);
  assert.match(provisionalImageLabel('fr'), /générée par IA/);
  assert.match(provisionalImageLabel('ar'), /الذكاء الاصطناعي/);
});
