import type { Media } from '@/lib/catalogue';

export function CatalogueImage({ media, placeholder, eager = false, thumbnail = false, alt, sizes }: { media: Media | null; placeholder: string; eager?: boolean; thumbnail?: boolean; alt?: string; sizes?: string }) {
  if (!media) return <div className="media-placeholder" role="img" aria-label={placeholder} data-image-placeholder>{placeholder}</div>;
  const variants = media.variants.filter(variant => variant.format === 'webp').sort((a, b) => a.width - b.width);
  const unique = variants.filter((variant, index) => variants.findIndex(item => item.width === variant.width) === index);
  const chosen = thumbnail ? unique[0] : unique.find(variant => variant.width >= 800) || unique.at(-1);
  if (!chosen) return <div className="media-placeholder" role="img" aria-label={placeholder}>{placeholder}</div>;
  // M2 already optimizes these immutable variants. Deliver directly from the media origin.
  return <img data-media-id={media.id} src={chosen.url} srcSet={unique.map(item => `${item.url} ${item.width}w`).join(', ')} sizes={sizes || (thumbnail ? '160px' : '(max-width: 767px) 92vw, 800px')} width={chosen.width} height={chosen.height} alt={alt ?? media.alt} loading={eager ? 'eager' : 'lazy'} fetchPriority={eager ? 'high' : undefined} decoding="async" />;
}
