'use client';

import { trapDialogFocus } from '@/lib/dialog-focus';

import { useEffect, useRef, useState, useSyncExternalStore, type ReactNode } from 'react';
import Link from 'next/link';
import type { CategoryPage } from '@/lib/catalogue';
import { catalogueCopy } from '@/lib/catalogue';
import { siteCopy } from '@/lib/site';
import { SiteIcon } from './site-icon';
import { CatalogueImage } from './catalogue-image';

function subscribe(callback: () => void) { const query = window.matchMedia('(max-width: 1023px)'); query.addEventListener('change', callback); return () => query.removeEventListener('change', callback); }
function mobileSnapshot() { return window.matchMedia('(max-width: 1023px)').matches; }

export function CatalogueCategories({ locale, data, query }: { locale: string; data: CategoryPage; query: string }) {
  const rail = useRef<HTMLElement>(null);
  const [edges, setEdges] = useState({ start: true, end: true, overflow: false });
  const filters = new URLSearchParams(query);
  const categories = data.data.flatMap(parent => parent.children?.length ? parent.children.map(child => ({ ...child, cover: child.cover || parent.cover, parameter: 'category' })) : [{ ...parent, parameter: 'parent' }]);
  const href = (parameter?: string, slug?: string) => {
    const next = new URLSearchParams(query);
    for (const key of ['parent', 'category', 'page']) next.delete(key);
    if (parameter && slug) next.set(parameter, slug);
    return `/${locale}/products${next.size ? `?${next}` : ''}`;
  };
  useEffect(() => {
    const node = rail.current;
    if (!node) return;
    const update = () => {
      const maximum = node.scrollWidth - node.clientWidth;
      const position = Math.abs(node.scrollLeft);
      setEdges({ start: position < 2, end: position >= maximum - 2, overflow: maximum > 2 });
    };
    const observer = new ResizeObserver(update);
    observer.observe(node);
    node.addEventListener('scroll', update, { passive: true });
    return () => { observer.disconnect(); node.removeEventListener('scroll', update); };
  }, [query, data.data.length]);
  const move = (direction: number) => rail.current?.scrollBy({ left: direction * (locale === 'ar' ? -1 : 1) * rail.current.clientWidth * .75, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
  const title = locale === 'ar' ? 'اكتشف المواد حسب الفئة' : 'Explorez par catégorie';
  return <section className="catalogue-category-discovery" aria-label={title}><div className="catalogue-category-heading"><div><p className="eyebrow">{locale === 'ar' ? 'اختر مادتك' : 'TROUVEZ VOTRE MATIÈRE'}</p><h2>{title}</h2></div><div className="category-swipe-controls" hidden={!edges.overflow}><button type="button" disabled={edges.start} onClick={() => move(-1)} aria-label={locale === 'ar' ? 'الفئات السابقة' : 'Catégories précédentes'}><SiteIcon name="arrow" className="directional category-back" /></button><button type="button" disabled={edges.end} onClick={() => move(1)} aria-label={locale === 'ar' ? 'الفئات التالية' : 'Catégories suivantes'}><SiteIcon name="arrow" className="directional" /></button></div></div>
    <nav ref={rail} className="catalogue-category-swiper" aria-label={title}><Link className="catalogue-category-tile all-materials-tile" href={href()} scroll={false} prefetch={false} aria-current={!filters.has('parent') && !filters.has('category') ? 'page' : undefined}><span className="category-tile-image"><SiteIcon name="collection" /></span><span>{locale === 'ar' ? 'جميع المواد' : 'Toutes les matières'}</span><SiteIcon name="arrow" className="directional" /></Link>{categories.map(category => <Link key={category.id} className="catalogue-category-tile" href={href(category.parameter, category.slug)} scroll={false} prefetch={false} aria-current={filters.get(category.parameter) === category.slug ? 'page' : undefined}><span className="category-tile-image">{category.cover ? <CatalogueImage media={category.cover} alt="" placeholder="" thumbnail sizes="64px" /> : <SiteIcon name="stone" />}</span><span>{category.name}</span><SiteIcon name="arrow" className="directional" /></Link>)}</nav>
  </section>;
}

export function CatalogueSort({ locale, query }: { locale: string; query: string }) {
  const copy = siteCopy(locale); const filters = new URLSearchParams(query);
  return <form method="get" className="catalogue-sort">{[...filters].filter(([key]) => key !== 'sort' && key !== 'page').map(([key, value]) => <input type="hidden" key={key} name={key} value={value} />)}<label>{copy.sort}<select name="sort" defaultValue={filters.get('sort') || ''} onChange={event => event.currentTarget.form?.requestSubmit()}><option value="">{copy.defaultSort}</option><option value="name">{copy.alphabetical}</option></select></label><noscript><button type="submit">{catalogueCopy(locale).apply}</button></noscript></form>;
}

export function CatalogueDisplay({ locale, toolbar, children }: { locale: string; toolbar: ReactNode; children: ReactNode }) {
  const [layout, setLayout] = useState<'grid' | 'list'>('grid');
  return <div className="catalogue-results" data-layout={layout}><div className="results-bar">{toolbar}<div className="catalogue-view-switch" role="group" aria-label={locale === 'ar' ? 'طريقة العرض' : 'Affichage'}>
    <button type="button" aria-label={locale === 'ar' ? 'عرض شبكي' : 'Vue grille'} aria-pressed={layout === 'grid'} onClick={() => setLayout('grid')}><SiteIcon name="collection" /></button>
    <button type="button" aria-label={locale === 'ar' ? 'عرض قائمة' : 'Vue liste'} aria-pressed={layout === 'list'} onClick={() => setLayout('list')}><SiteIcon name="list" /></button>
  </div></div>{children}</div>;
}

export function CatalogueFilters({ locale, data, query }: { locale: string; data: CategoryPage; query: string }) {
  const mobile = useSyncExternalStore(subscribe, mobileSnapshot, () => false);
  const panel = useRef<HTMLDialogElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const copy = catalogueCopy(locale); const design = siteCopy(locale); const filters = new URLSearchParams(query);
  const [parentSlug, setParentSlug] = useState(filters.get('parent') || '');
  const [categorySlug, setCategorySlug] = useState(filters.get('category') || '');
  const parent = data.data.find(item => item.slug === parentSlug);
  const children = parent?.children || data.data.flatMap(item => item.children || []);
  const form = <form className="catalogue-filters" method="get"><p className="filter-eyebrow">{locale === 'ar' ? 'مجموعة المواد' : 'LA COLLECTION'}</p><h2>{design.filterTitle}</h2>
    {['q', 'sort'].filter(key => filters.has(key)).map(key => <input key={key} type="hidden" name={key} value={filters.get(key)!} />)}
    <label>{copy.parent}<span className="filter-select"><SiteIcon name="stone" /><select name="parent" value={parentSlug} onChange={event => { setParentSlug(event.target.value); setCategorySlug(''); }}><option value="">{copy.all}</option>{data.data.map(item => <option key={item.id} value={item.slug}>{item.name}</option>)}</select></span></label>
    <label>{copy.child}<span className="filter-select"><SiteIcon name="masonry" /><select name="category" value={categorySlug} onChange={event => setCategorySlug(event.target.value)}><option value="">{copy.all}</option>{children.map(item => <option key={item.id} value={item.slug}>{item.name}</option>)}</select></span></label>
    {data.finishes.length > 0 && <label>{copy.finish}<span className="filter-select"><SiteIcon name="craft" /><select name="finish" defaultValue={filters.get('finish') || ''}><option value="">{copy.all}</option>{data.finishes.map(item => <option key={item.id} value={item.slug}>{item.name}</option>)}</select></span></label>}
    <button className="button bronze" type="submit">{locale === 'ar' ? 'تطبيق التصفية' : 'Appliquer les filtres'}<SiteIcon name="arrow" className="directional" /></button><a className="filter-reset" href={`/${locale}/products`}>{design.clear}</a>
    <div className="filter-advice"><SiteIcon name="craft" /><h3>{locale === 'ar' ? 'لكل مشروع مادته' : 'À chaque projet, sa matière.'}</h3><p>{locale === 'ar' ? 'لنتحدث عن المظهر والمقاسات التي تبحث عنها.' : 'Échangeons sur les nuances et les formats que vous recherchez.'}</p><a href={`/${locale}/contact`}>{design.contactAction}<SiteIcon name="arrow" className="directional" /></a></div>
  </form>;
  return mobile ? <><button ref={trigger} className="button outline mobile-filter-toggle" type="button" aria-haspopup="dialog" onClick={() => panel.current?.showModal()}><SiteIcon name="filter" />{design.filters}{['parent', 'category', 'finish'].filter(key => filters.has(key)).length > 0 && <span className="filter-active-count">{['parent', 'category', 'finish'].filter(key => filters.has(key)).length}</span>}</button><dialog onKeyDown={trapDialogFocus} onClose={() => trigger.current?.focus()} ref={panel} className="filter-dialog" aria-label={design.filters}><div className="drawer-heading"><b>{design.filters}</b><button type="button" className="icon-button" aria-label={design.close} onClick={() => panel.current?.close()}><SiteIcon name="close" /></button></div>{form}</dialog></> : <aside className="desktop-filter-panel">{form}</aside>;
}
