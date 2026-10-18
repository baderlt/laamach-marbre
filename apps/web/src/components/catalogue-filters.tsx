'use client';

import { trapDialogFocus } from '@/lib/dialog-focus';

import { useRef, useSyncExternalStore } from 'react';
import type { CategoryPage } from '@/lib/catalogue';
import { catalogueCopy } from '@/lib/catalogue';
import { siteCopy } from '@/lib/site';
import { SiteIcon } from './site-icon';

function subscribe(callback: () => void) { const query = window.matchMedia('(max-width: 1023px)'); query.addEventListener('change', callback); return () => query.removeEventListener('change', callback); }
function mobileSnapshot() { return window.matchMedia('(max-width: 1023px)').matches; }

export function CatalogueSort({ locale, query }: { locale: string; query: string }) {
  const copy = siteCopy(locale); const filters = new URLSearchParams(query);
  return <form method="get" className="catalogue-sort">{[...filters].filter(([key]) => key !== 'sort' && key !== 'page').map(([key, value]) => <input type="hidden" key={key} name={key} value={value} />)}<label>{copy.sort}<select name="sort" defaultValue={filters.get('sort') || ''} onChange={event => event.currentTarget.form?.requestSubmit()}><option value="">{copy.defaultSort}</option><option value="name">{copy.alphabetical}</option></select></label><noscript><button type="submit">{catalogueCopy(locale).apply}</button></noscript></form>;
}

export function CatalogueFilters({ locale, data, query }: { locale: string; data: CategoryPage; query: string }) {
  const mobile = useSyncExternalStore(subscribe, mobileSnapshot, () => false);
  const panel = useRef<HTMLDialogElement>(null);
  const copy = catalogueCopy(locale); const design = siteCopy(locale); const filters = new URLSearchParams(query);
  const parent = data.data.find(item => item.slug === filters.get('parent'));
  const children = parent?.children || data.data.flatMap(item => item.children || []);
  const form = <form className="catalogue-filters" method="get"><h2>{design.filterTitle}</h2><label>{copy.parent}<select name="parent" defaultValue={filters.get('parent') || ''}><option value="">{copy.all}</option>{data.data.map(item => <option key={item.id} value={item.slug}>{item.name}</option>)}</select></label><label>{copy.child}<select name="category" defaultValue={filters.get('category') || ''}><option value="">{copy.all}</option>{children.map(item => <option key={item.id} value={item.slug}>{item.name}</option>)}</select></label><label>{copy.finish}<select name="finish" defaultValue={filters.get('finish') || ''}><option value="">{copy.all}</option>{data.finishes.map(item => <option key={item.id} value={item.slug}>{item.name}</option>)}</select></label><label>{design.search}<input name="q" defaultValue={filters.get('q') || ''} maxLength={120} /></label><label>{design.sort}<select name="sort" defaultValue={filters.get('sort') || ''}><option value="">{design.defaultSort}</option><option value="name">{design.alphabetical}</option></select></label><button className="button bronze" type="submit">{copy.apply}<SiteIcon name="filter" /></button><a className="text-link" href={`/${locale}/products`}>{design.clear}</a></form>;
  return mobile ? <><button className="button outline mobile-filter-toggle" type="button" onClick={() => panel.current?.showModal()}><SiteIcon name="filter" />{design.filters}</button><dialog onKeyDown={trapDialogFocus} ref={panel} className="filter-dialog" aria-label={design.filters}><div className="drawer-heading"><b>{design.filters}</b><button type="button" className="icon-button" aria-label={design.close} onClick={() => panel.current?.close()}><SiteIcon name="close" /></button></div>{form}</dialog></> : <aside className="desktop-filter-panel">{form}</aside>;
}
