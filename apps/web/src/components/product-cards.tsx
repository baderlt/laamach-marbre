import type { ProductCard } from '@/lib/catalogue';
import { siteCopy } from '@/lib/site';
import { catalogueCopy } from '@/lib/catalogue';
import { CatalogueImage } from './catalogue-image';
import { SiteIcon } from './site-icon';

export function ProductCards({ products, locale, compact = false, slots = 0 }: { products: ProductCard[]; locale: string; compact?: boolean; slots?: number }) {
  const copy = siteCopy(locale);
  return <div className={`product-grid${compact ? ' compact-grid' : ''}`}>{products.map(product => <article key={product.reference} className="product-card"><a className="card-media" href={`/${locale}/products/${product.slug}`}><CatalogueImage media={product.cover} alt={product.name} placeholder={catalogueCopy(locale).missingImage} /><span className="category-tag">{product.category.name}</span></a><div className="card-copy"><h2><a href={`/${locale}/products/${product.slug}`}>{product.name}</a></h2><p>{copy.materialCardNote}</p><a className="card-action" href={`/${locale}/products/${product.slug}`}>{copy.viewProduct}<SiteIcon name="arrow" className="directional" /></a></div></article>)}{Array.from({ length: Math.max(0, slots - products.length) }, (_, index) => <div key={`pending-${index}`} className="product-card pending-card" data-visual-placeholder><div className={`card-media neutral-material study-${index % 3}`}><SiteIcon name="stone" /><small>{copy.missingMedia}</small></div><div className="card-copy"><h3>{copy.pendingReference}</h3><p>{copy.pendingEditorial}</p><span className="card-action">{copy.pendingValidation}</span></div></div>)}</div>;
}
