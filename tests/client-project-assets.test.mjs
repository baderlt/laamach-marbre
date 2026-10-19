import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';

const plan = JSON.parse(readFileSync(new URL('../config/client-projects-map.json', import.meta.url)));
test('reviewed client project sources retain original bytes and explicit provenance', () => {
  assert.equal(plan.inventory.length, 113);
  for (const entry of plan.inventory) {
    const bytes = readFileSync(new URL(`../${entry.organized}`, import.meta.url));
    assert.equal(createHash('sha256').update(bytes).digest('hex'), entry.sha256);
    if (entry.role === 'review') assert.ok(entry.reason && entry.organized.includes('/_review/'));
  }
  assert.equal(plan.assets.length, 60);
  assert.ok(plan.assets.every(asset => asset.client_supplied && !asset.source.includes('/_review/')));
});
test('collections have one cover, bounded gallery, bilingual copy and video-specific posters', () => {
  assert.equal(plan.projects.length, 5);
  for (const project of plan.projects) {
    assert.match(project.slug, /^[a-z0-9]+(?:-[a-z0-9]+)*$/);
    assert.ok(project.title.fr && project.title.ar);
    assert.equal(project.entries.filter(entry => entry.role === 'project_cover').length, 1);
    assert.ok(project.entries.length - 1 <= 12);
    for (const entry of project.entries.filter(entry => entry.role === 'project_video')) assert.ok(entry.poster && plan.assets.some(asset => asset.source === entry.poster));
  }
  const company = JSON.parse(readFileSync(new URL('../config/client-company-content.json', import.meta.url)));
  for (const locale of ['fr', 'ar']) {
    assert.equal(company.trust[locale].length, 4);
    assert.ok(company.company[locale]);
    assert.ok(Object.values(company.seo).every(page => page[locale].seo_title && page[locale].seo_description.length <= 320));
  }
});
