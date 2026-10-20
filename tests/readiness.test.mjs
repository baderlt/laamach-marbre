import test from 'node:test';
import assert from 'node:assert/strict';
import { serverEnvironment } from '../apps/web/src/lib/environment.ts';
import { seoMetadata, jsonLd, breadcrumbs } from '../apps/web/src/lib/seo.ts';
import { contentSecurityPolicy, trustedHost, withinRequestBudget } from '../apps/web/src/lib/http-security.ts';

const environment = { SITE_URL: 'https://stone.example', LARAVEL_API_URL: 'https://api.stone.example' };

test('indexing requires explicit launch authorization and production HTTPS', () => {
  assert.equal(serverEnvironment(environment).indexing, false);
  assert.equal(serverEnvironment({ ...environment, SITE_ENV: 'staging', SITE_INDEXING_ENABLED: 'true' }).indexing, false);
  assert.equal(serverEnvironment({ ...environment, SITE_ENV: 'production', SITE_INDEXING_ENABLED: 'true' }).indexing, true);
  assert.throws(() => serverEnvironment({ ...environment, SITE_ENV: 'production', SITE_URL: 'http://127.0.0.1:3105' }));
  assert.throws(() => serverEnvironment({ ...environment, MEDIA_ORIGINS: '*' }));
  assert.throws(() => serverEnvironment({ ...environment, MEDIA_ORIGINS: 'https://*.stone.example' }));
});

test('metadata uses canonical configuration, eligible locales and approved public origins', () => {
  const before = { ...process.env };
  try {
    Object.assign(process.env, environment, { SITE_ENV: 'production', SITE_INDEXING_ENABLED: 'false', MEDIA_ORIGINS: 'https://media.stone.example' });
    const metadata = seoMetadata('fr', '/products/stone', 'Pierre', 'Une pierre sélectionnée.', { id: 'photo', alt: 'Pierre', variants: [{ url: 'https://media.stone.example/published/photo.webp', width: 1200, height: 800, format: 'webp' }] }, ['fr']);
    assert.deepEqual(metadata.alternates, { canonical: 'https://stone.example/fr/products/stone', languages: { fr: 'https://stone.example/fr/products/stone' } });
    assert.equal(metadata.robots.index, false);
    assert.equal(metadata.openGraph.images[0].url, 'https://media.stone.example/published/photo.webp');
    const unsafe = seoMetadata('ar', '', 'حجر', 'حجر طبيعي', { variants: [{ url: 'https://untrusted.invalid/private/a.webp', format: 'webp', width: 1200 }] }, ['ar']);
    assert.deepEqual(unsafe.openGraph.images, []);
  } finally {
    for (const key of Object.keys(process.env)) if (!(key in before)) delete process.env[key];
    Object.assign(process.env, before);
  }
});

test('JSON-LD escapes script termination without inventing commerce attributes', () => {
  const data = { '@context': 'https://schema.org', '@type': 'Product', name: '</script><script>alert(1)</script>\u2028' };
  const encoded = jsonLd(data);
  assert.equal(encoded.includes('</script>'), false);
  assert.deepEqual(JSON.parse(encoded), data);
  assert.equal(JSON.parse(encoded).offers, undefined);
  assert.equal(breadcrumbs([{ name: 'Accueil', url: 'https://stone.example/fr' }]).itemListElement[0].position, 1);
});

test('CSP is bounded and nonce-based; Host and request budgets reject spoofing and excess', () => {
  const config = serverEnvironment({ ...environment, SITE_ENV: 'production', MEDIA_ORIGINS: 'https://media.stone.example' });
  const csp = contentSecurityPolicy('test-nonce', config, false);
  assert.match(csp, /script-src 'self' 'nonce-test-nonce';/);
  assert.equal(csp.includes('unsafe-eval'), false);
  assert.equal(csp.includes('https://api.stone.example'), false);
  assert.match(csp, /object-src 'none'/);
  assert.match(csp, /connect-src 'self' blob:/);
  assert.equal(trustedHost(new Request('https://stone.example/api/requests', { headers: { host: 'spoof.invalid' } }), config.site), false);
  assert.equal(trustedHost(new Request('https://stone.example/api/requests', { headers: { host: 'stone.example', 'x-forwarded-host': 'spoof.invalid' } }), config.site), true);
  assert.equal(trustedHost(new Request('http://127.0.0.1:3100/api/requests', { headers: { host: 'stone.example' } }), config.site), true);
  assert.equal(withinRequestBudget('test', 1, 100), true);
  assert.equal(withinRequestBudget('test', 1, 101), false);
  assert.equal(withinRequestBudget('test', 1, 60101), true);
});
