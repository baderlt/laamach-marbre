import test from 'node:test';
import assert from 'node:assert/strict';
import { productMeasurements, measurementGuides } from '../apps/web/src/lib/viewer-measurements.ts';

const frame = { width: 400, height: 340, center: [200, 170], dimensions: { width: .6, length: .2, depth: .03 }, edges: [
  { axis: 'width', start: [80, 190], end: [280, 230] },
  { axis: 'length', start: [80, 190], end: [180, 100] },
  { axis: 'depth', start: [280, 210], end: [280, 230] },
] };

test('provided guides use centimetres in FR/AR and never mutate scene or catalogue dimensions', () => {
  const snapshot = JSON.stringify(frame);
  assert.deepEqual(measurementGuides(frame, 'fr').map(item => item.label), ['60 cm', '20 cm', '3 cm']);
  assert.ok(measurementGuides(frame, 'ar').every(item => item.label.endsWith('سم')));
  assert.equal(JSON.stringify(frame), snapshot);
});

test('only supplied positive measurements appear, with explicit unit conversion', () => {
  assert.deepEqual(productMeasurements(), {});
  assert.deepEqual(productMeasurements({ width: null, length: null, thickness: '3', measurement_unit: 'cm' }), { depth: .03 });
  const millimetres = productMeasurements({ width: '700', length: '250', thickness: null, measurement_unit: 'mm' });
  assert.ok(Math.abs(millimetres.width - .7) < 1e-12);
  assert.equal(millimetres.length, .25);
  assert.equal(millimetres.depth, undefined);
  assert.deepEqual(productMeasurements({ width: '1', length: '2', thickness: '.05', measurement_unit: 'm' }), { width: 1, length: 2, depth: .05 });
  assert.deepEqual(productMeasurements({ width: '0', length: '-1', thickness: 'bad', measurement_unit: 'cm' }), {});
  assert.deepEqual(productMeasurements({ width: '7', length: '25', thickness: null, measurement_unit: 'unknown' }), {});
  assert.deepEqual(measurementGuides({ ...frame, dimensions: { depth: .03 } }, 'fr').map(guide => guide.axis), ['depth']);
  assert.deepEqual(measurementGuides({ ...frame, dimensions: {} }, 'fr'), []);
});

test('callouts remain inside the view and reject invalid, offscreen or degenerate measurements', () => {
  for (const item of measurementGuides(frame, 'fr')) {
    assert.ok(item.x - item.labelWidth / 2 >= 12);
    assert.ok(item.x + item.labelWidth / 2 <= frame.width - 12);
    assert.ok(item.y >= 20 && item.y <= frame.height - 20);
  }
  assert.deepEqual(measurementGuides({ ...frame, width: 0 }, 'fr'), []);
  assert.deepEqual(measurementGuides({ ...frame, dimensions: { width: 0, length: NaN, depth: Infinity } }, 'fr'), []);
  assert.deepEqual(measurementGuides({ ...frame, edges: [{ axis: 'width', start: [-1, 20], end: [200, 20] }, { axis: 'length', start: [20, 20], end: [20, 20] }] }, 'fr'), []);
});
