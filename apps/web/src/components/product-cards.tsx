import type { ProductCard } from '@/lib/catalogue';
import { siteCopy } from '@/lib/site';
import { catalogueCopy } from '@/lib/catalogue';
import { CatalogueImage } from './catalogue-image';
import { SiteIcon } from './site-icon';

export function ProductCards({ products, locale, compact = false, slots = 0, premium = false, headingLevel = 2, imageSizes, minimal = false }: { products: ProductCard[]; locale: string; compact?: boolean; slots?: number; premium?: boolean; headingLevel?: 2 | 3; imageSizes?: string; minimal?: boolean }) {
  const copy = siteCopy(locale);
  const Heading = headingLevel === 3 ? 'h3' : 'h2';
  if (premium) return <div className="product-grid selection-grid" data-motion-group>{products.map(product => <article key={product.reference} className="product-card selection-card">
    <a className="card-media" href={`/${locale}/products/${product.slug}`} tabIndex={-1} aria-hidden="true"><CatalogueImage media={product.cover} alt="" sizes="(max-width: 599px) 46vw, (max-width: 1023px) 30vw, (max-width: 1279px) 26vw, 280px" placeholder={catalogueCopy(locale).missingImage} /><span className="category-tag">{product.category.name}</span></a>
    <div className="card-copy"><Heading><a href={`/${locale}/products/${product.slug}`}>{product.name}<span className="selection-arrow"><SiteIcon name="arrow" className="directional" /><span className="sr-only"> — {copy.viewProduct}</span></span></a></Heading><span className="selection-reference"><span>{copy.reference}</span> <bdi>{product.reference}</bdi></span></div>
  </article>)}</div>;
  return <div className={`product-grid${compact ? ' compact-grid' : ''}`}>{products.map(product => <article key={product.reference} className="product-card">
    <a className="card-media" href={`/${locale}/products/${product.slug}`} aria-label={minimal ? `${locale === 'ar' ? 'عرض التفاصيل' : 'Voir les détails'} : ${product.name}` : undefined}><CatalogueImage media={product.cover} alt={minimal ? '' : product.name} sizes={imageSizes || '(max-width: 359px) 92vw, (max-width: 767px) 44vw, (max-width: 1023px) 30vw, 300px'} placeholder={catalogueCopy(locale).missingImage} />{minimal ? <span className="home-product-details" aria-hidden="true"><SiteIcon name="arrow" className="directional" /></span> : <span className="category-tag">{product.category.name}</span>}</a>
    <div className="card-copy"><h2><a href={`/${locale}/products/${product.slug}`}>{product.name}</a></h2>{minimal ? <p className="home-product-category">{product.category.name}</p> : <><p>{copy.materialCardNote}</p><a className="card-action" href={`/${locale}/products/${product.slug}`}>{copy.viewProduct}<SiteIcon name="arrow" className="directional" /></a></>}</div>
  </article>)}{Array.from({ length: products.length ? 0 : Math.min(1, slots) }, (_, index) => <div key={`pending-${index}`} className="product-card pending-card" data-visual-placeholder><div className={`card-media neutral-material study-${index % 3}`}><SiteIcon name="stone" /><small>{copy.missingMedia}</small></div><div className="card-copy"><h3>{copy.pendingReference}</h3><p>{copy.pendingEditorial}</p><span className="card-action">{copy.pendingValidation}</span></div></div>)}</div>;
}
