import type { Product } from './catalogue.ts';

export const quantityUnits = ['m2', 'm3', 'linear_m', 'piece'] as const;
export const textLimits = { city: 160, dimensions: 200, description: 4000, projectType: 160, application: 160 };
export type DraftFields = { size: string; custom: boolean; width: string; length: string; finish: string; quantity: string; unit: string; city: string; dimensions: string; description: string; projectType?: string; application?: string };
export function positiveDecimal(value: string): boolean {
  return /^(?:0|[1-9]\d{0,8})(?:\.\d{1,6})?$/.test(value) && Number(value) > 0;
}
export function validateDraftText(fields: DraftFields): string[] {
  return Object.entries(textLimits).filter(([key, max]) => {
    const value = fields[key as keyof typeof textLimits] || '';
    return value.length > max || /[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f\u202a-\u202e\u2066-\u2069]/u.test(value) || (key !== 'description' && /[\r\n\t]/.test(value));
  }).map(([key]) => key);
}
export function validateMaterialDraft(product: Product, fields: DraftFields, pending = false): string[] {
  const errors: string[] = [];
  if (fields.custom) {
    const bounds = product.custom_size;
    if (!product.custom_size_allowed || !bounds) errors.push('custom');
    else for (const axis of ['width', 'length'] as const) {
      const value = Number(fields[axis]);
      const min = bounds[`min_${axis}`]; const max = bounds[`max_${axis}`];
      if (!positiveDecimal(fields[axis]) || (min != null && value < Number(min)) || (max != null && value > Number(max))) errors.push(axis);
    }
  } else if ((product.sizes.length || fields.size) && !product.sizes.some(size => size.id === fields.size)) errors.push('size');
  if ((product.finishes.length || fields.finish) && !product.finishes.some(finish => finish.id === fields.finish)) errors.push('finish');
  if (!pending) {
    if (!positiveDecimal(fields.quantity)) errors.push('quantity');
    if (!quantityUnits.includes(fields.unit as typeof quantityUnits[number]) || !product.quantity_units.includes(fields.unit)) errors.push('unit');
  }
  return [...errors, ...validateDraftText(fields)];
}
