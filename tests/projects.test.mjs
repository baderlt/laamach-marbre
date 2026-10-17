import test from 'node:test';
import assert from 'node:assert/strict';
import { projectFilters, projectsCopy } from '../apps/web/src/lib/projects.ts';

test('project filters preserve bounded OR selections without duplicate or unsafe slugs', () => {
  assert.deepEqual(projectFilters({ 'categories[]': ['pool', 'villa', 'pool', '../unsafe'], page: '2' }).getAll('categories[]'), ['pool', 'villa']);
  assert.equal(projectFilters({ category: 'pool', page: '10000' }).get('page'), '10000');
  assert.equal(projectFilters({ categories: 'x'.repeat(121), page: '10001' }).toString(), '');
  assert.equal(projectFilters({ categories: Array.from({ length: 20 }, (_, i) => `category-${i}`) }).getAll('categories[]').length, 8);
});

test('project copy uses configuration fallback and provides FR and AR gallery messages', () => {
  assert.equal(projectsCopy('fr').video, 'Vidéo');
  assert.equal(projectsCopy('ar').video, 'فيديو');
  assert.equal(projectsCopy('es').projects, projectsCopy('fr').projects);
});
