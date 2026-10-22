'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { appendProductPage, catalogueCopy, type ProductPage } from '@/lib/catalogue';
import { ProductCards } from './product-cards';
import { SiteIcon } from './site-icon';

export function CatalogueFeed({ initial, locale, query }: { initial: ProductPage; locale: string; query: string }) {
  const [page, setPage] = useState(initial);
  const [loading, setLoading] = useState(false);
  const [failed, setFailed] = useState(false);
  const sentinel = useRef<HTMLDivElement>(null);
  const pending = useRef(false);
  const controller = useRef<AbortController | null>(null);
  const ar = locale === 'ar';
  const hasMore = page.meta.current_page < page.meta.last_page;
  const pageLink = (number: number) => { const params = new URLSearchParams(query); params.set('page', String(number)); return `/${locale}/products?${params}`; };
  const loadMore = useCallback(async () => {
    if (pending.current || page.meta.current_page >= page.meta.last_page) return;
    pending.current = true;
    const abort = new AbortController();
    controller.current = abort;
    setLoading(true);
    setFailed(false);
    const params = new URLSearchParams(query);
    params.set('locale', locale);
    params.set('page', String(page.meta.current_page + 1));
    try {
      const response = await fetch(`/api/catalogue?${params}`, { signal: abort.signal, cache: 'no-store' });
      if (!response.ok) throw new Error('Catalogue unavailable');
      const next = await response.json() as ProductPage;
      if (abort.signal.aborted) return;
      if (!Array.isArray(next.data) || next.meta?.current_page !== page.meta.current_page + 1) throw new Error('Invalid catalogue page');
      setPage(current => appendProductPage(current, next));
    } catch {
      if (!abort.signal.aborted) setFailed(true);
    } finally {
      pending.current = false;
      if (!abort.signal.aborted) setLoading(false);
    }
  }, [locale, query, page.meta.current_page, page.meta.last_page]);

  useEffect(() => () => controller.current?.abort(), []);
  useEffect(() => {
    if (!hasMore || failed || loading || !sentinel.current || !('IntersectionObserver' in window)) return;
    const observer = new IntersectionObserver(entries => {
      if (entries.some(entry => entry.isIntersecting)) void loadMore();
    }, { rootMargin: '200px' });
    observer.observe(sentinel.current);
    return () => observer.disconnect();
  }, [hasMore, failed, loading, loadMore]);

  return <div className="catalogue-feed" aria-busy={loading}>
    {page.groups ? <div className="catalogue-groups">{page.groups.map(group => <section className="catalogue-category-section" key={group.category.id} aria-labelledby={`category-${group.category.slug}`}>
      <header className="catalogue-category-heading"><div><span className="eyebrow">{ar ? `${group.total} منتج` : `${group.total} produits`}</span><h2 id={`category-${group.category.slug}`}>{group.category.name}</h2>{group.category.description && <p>{group.category.description}</p>}</div><a className="button outline" href={`/${locale}/products?category=${group.category.slug}`}>{ar ? 'عرض الفئة كاملة' : 'Voir toute la catégorie'}<SiteIcon name="arrow" className="directional" /></a></header>
      <ProductCards products={page.data.filter(product => product.category.id === group.category.id)} locale={locale} premium headingLevel={3} />
    </section>)}</div> : <ProductCards products={page.data} locale={locale} premium />}
    <div ref={sentinel} className="catalogue-load-more">
      <p role="status" aria-atomic="true">{loading ? (ar ? 'جارٍ تحميل المنتجات…' : 'Chargement des produits…') : failed ? (ar ? 'تعذر تحميل المنتجات. يرجى المحاولة مجددًا.' : 'Le chargement a échoué. Réessayez.') : (ar ? `عرض ${page.data.length} من ${page.meta.total} منتج` : `${page.data.length} produits affichés sur ${page.meta.total}`)}</p>
      {hasMore && <button className="button outline" type="button" disabled={loading} onClick={() => void loadMore()}>{failed ? (ar ? 'إعادة المحاولة' : 'Réessayer') : (ar ? 'عرض المزيد من المنتجات' : 'Voir plus de produits')}</button>}
    </div>
    <noscript><style>{'.catalogue-load-more{display:none}'}</style><nav className="pagination selection-pagination" aria-label={ar ? 'المزيد من المنتجات' : 'Suite du catalogue'}>{initial.meta.current_page > 1 && <a className="button outline" href={pageLink(initial.meta.current_page - 1)}>{catalogueCopy(locale).previous}</a>}{initial.meta.current_page < initial.meta.last_page && <a className="button outline" href={pageLink(initial.meta.current_page + 1)}>{catalogueCopy(locale).next}</a>}</nav></noscript>
  </div>;
}
