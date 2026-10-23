import type { Media } from '@/lib/catalogue';
import { preload } from 'react-dom';

export function CatalogueImage({ media, placeholder, eager = false, mobilePreload = false, thumbnail = false, alt, sizes }: { media: Media | null; placeholder: string; eager?: boolean; mobilePreload?: boolean; thumbnail?: boolean; alt?: string; sizes?: string }) {
  if (!media) return <div className="media-placeholder" role="img" aria-label={placeholder} data-image-placeholder>{placeholder}</div>;
  const variants = media.variants.filter(variant => variant.format === 'webp').sort((a, b) => a.width - b.width);
  const unique = variants.filter((variant, index) => variants.findIndex(item => item.width === variant.width) === index);
  const chosen = thumbnail ? unique[0] : unique.find(variant => variant.width >= 800) || unique.at(-1);
  if (!chosen) return <div className="media-placeholder" role="img" aria-label={placeholder}>{placeholder}</div>;
  const imageSrcSet = unique.map(item => `${item.url} ${item.width}w`).join(', ');
  const imageSizes = sizes || (thumbnail ? '160px' : '(max-width: 767px) 92vw, 800px');
  if (mobilePreload) preload(chosen.url, { as: 'image', media: '(max-width: 767px)', imageSrcSet, imageSizes, fetchPriority: 'high' });
  // M2 already optimizes these immutable variants. Deliver directly from the media origin.
  return <img data-media-id={media.id} src={chosen.url} srcSet={imageSrcSet} sizes={imageSizes} width={chosen.width} height={chosen.height} alt={alt ?? media.alt} loading={eager || mobilePreload ? 'eager' : 'lazy'} fetchPriority={eager || mobilePreload ? 'high' : undefined} decoding="async" />;
}
