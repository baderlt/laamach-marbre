import messages from '../../../../config/viewer-messages.json' with { type: 'json' };
import { localeConfiguration } from './locales.ts';
import type { Media, Variant } from './catalogue.ts';
export type Dimensions = { width: number; length: number; depth: number };
export type ViewerConfiguration = { mode: 'uploaded_glb' | 'procedural'; poster: Media | null; model: { url: string; hash: string; bytes: number; revision: string } | null; geometry: 'block' | 'slab' | null; dimensions_m: Dimensions | null; sizes: { id: string; label: string; dimensions_m: Dimensions }[]; default_size_id: string | null; material: { roughness: number; metalness: number; texture: Variant | null } | null; camera: { angle: 'front' | 'isometric'; rotation: 'full' | 'front'; min_distance_factor: number; max_distance_factor: number } };
export function viewerDimensions(config: ViewerConfiguration, sizeId?: string | null): Dimensions | null {
  if (config.mode === 'uploaded_glb') return null;
  const value = config.sizes.length && sizeId ? config.sizes.find(size => size.id === sizeId)?.dimensions_m : config.dimensions_m;
  return value && Object.values(value).length === 3 && Object.values(value).every(n => Number.isFinite(n) && n >= .000001 && n <= 100) ? value : null;
}
export function viewerCopy(locale: string) {
  const copy = messages[locale as keyof typeof messages] || messages[localeConfiguration.fallback as keyof typeof messages];
  if (!copy) throw new Error('The configured fallback requires viewer translations');
  return copy;
}
