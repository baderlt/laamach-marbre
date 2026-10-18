import type { Product } from './catalogue.ts';

export type DraftFields = { size: string; custom: boolean; width: string; length: string; finish: string; quantity: string; unit: string; city: string; dimensions: string; description: string };
export function validateMaterialDraft(product: Product, fields: DraftFields, pending = false): string[] {
  const errors: string[] = [];
  if (fields.custom) {
    const bounds = product.custom_size;
    if (!product.custom_size_allowed || !bounds) errors.push('custom');
    else for (const axis of ['width', 'length'] as const) {
      const value = Number(fields[axis]);
      const min = bounds[`min_${axis}`]; const max = bounds[`max_${axis}`];
      if (!fields[axis].trim() || !Number.isFinite(value) || value <= 0 || (min != null && value < Number(min)) || (max != null && value > Number(max))) errors.push(axis);
    }
  } else if (product.sizes.length && !product.sizes.some(size => size.id === fields.size)) errors.push('size');
  if (product.finishes.length && !product.finishes.some(finish => finish.id === fields.finish)) errors.push('finish');
  if (!pending) {
    if (!fields.quantity.trim() || !Number.isFinite(Number(fields.quantity)) || Number(fields.quantity) <= 0) errors.push('quantity');
    if (!product.quantity_units.includes(fields.unit)) errors.push('unit');
  }
  if (fields.description.length > 4000) errors.push('description');
  if (fields.city.length > 160) errors.push('city');
  if (fields.dimensions.length > 200) errors.push('dimensions');
  return errors;
}
