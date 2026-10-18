import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = file => JSON.parse(readFileSync(new URL(`../config/${file}`, import.meta.url), 'utf8'));

test('reviewed page copy covers both enabled languages without external image URLs', () => {
  const pages = read('website-editorial-revision.json');
  for (const key of ['home', 'products', 'projects', 'services', 'about', 'contact']) {
    for (const locale of ['fr', 'ar']) {
      const copy = pages[key][locale];
      assert.ok(copy.title?.trim(), `${key}/${locale}: title`);
      assert.ok(copy.body?.trim(), `${key}/${locale}: body`);
      assert.ok(copy.seo_title?.trim(), `${key}/${locale}: SEO title`);
      assert.ok(copy.seo_description?.trim(), `${key}/${locale}: SEO description`);
    }
  }
  for (const locale of ['fr', 'ar']) assert.ok(pages.site[locale].footer_text?.trim());
  assert.doesNotMatch(JSON.stringify(pages), /https?:\/\/|wa\.me|api\.whatsapp/i);
});

test('seven product proposals remain review drafts with no unverified specifications', () => {
  const proposals = read('product-editorial-review.json');
  assert.equal(new Set(proposals.map(item => item.slug)).size, 7);
  for (const proposal of proposals) {
    assert.equal(proposal.status, 'editorial-draft-pending-client-confirmation');
    assert.ok(proposal.pending.includes('Dimensions/unit'));
    assert.ok(proposal.pending.includes('Technical properties'));
    for (const locale of ['fr', 'ar']) {
      const copy = proposal.translations[locale];
      assert.deepEqual(Object.keys(copy).sort(), ['description', 'name', 'seo_description', 'seo_title']);
      assert.doesNotMatch(copy.description, /\b(?:7\s*[×x]\s*15|10\s*[×x]\s*20|antidérapant|hydrofuge|certifié)\b/i);
    }
  }
});
