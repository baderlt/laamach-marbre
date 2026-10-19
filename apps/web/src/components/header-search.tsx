'use client';

import { useEffect, useId, useRef, useState } from 'react';
import type { ProductCard } from '@/lib/catalogue';
import { catalogueCopy } from '@/lib/catalogue';
import { trapDialogFocus } from '@/lib/dialog-focus';
import { siteCopy } from '@/lib/site';
import { CatalogueImage } from './catalogue-image';
import { SiteIcon } from './site-icon';

export function HeaderSearch({ locale }: { locale: string }) {
  const dialog = useRef<HTMLDialogElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const id = useId();
  const [open, setOpen] = useState(false);
  const [value, setValue] = useState('');
  const [composing, setComposing] = useState(false);
  const [results, setResults] = useState<ProductCard[]>([]);
  const [total, setTotal] = useState(0);
  const [active, setActive] = useState(-1);
  const [phase, setPhase] = useState<'idle' | 'waiting' | 'loading' | 'ready' | 'error'>('idle');
  const query = value.trim();
  const copy = siteCopy(locale);
  const ar = locale === 'ar';
  useEffect(() => {
    if (!open || composing || query.length < 2) return;
    const controller = new AbortController();
    const timer = window.setTimeout(async () => {
      setPhase('loading');
      try {
        const response = await fetch(`/api/search?${new URLSearchParams({ locale, q: query })}`, { signal: controller.signal, cache: 'no-store' });
        if (!response.ok) throw new Error('search');
        const data: { data: ProductCard[]; total: number } = await response.json();
        if (!controller.signal.aborted) { setResults(data.data); setTotal(data.total); setPhase('ready'); }
      } catch {
        if (!controller.signal.aborted) setPhase('error');
      }
    }, 400);
    return () => { clearTimeout(timer); controller.abort(); };
  }, [query, value, locale, open, composing]);
  const reset = () => { setOpen(false); setValue(''); setResults([]); setActive(-1); setPhase('idle'); trigger.current?.focus(); };
  const status = phase === 'error' ? (ar ? 'تعذر البحث الآن. حاول مجدداً.' : 'La recherche est indisponible. Réessayez.')
    : phase === 'loading' ? (ar ? 'جارٍ البحث…' : 'Recherche en cours…')
    : phase === 'ready' ? (ar ? `${total} نتيجة` : `${total} résultat${total === 1 ? '' : 's'}`)
    : (ar ? 'اكتب حرفين على الأقل للبحث في المواد.' : 'Saisissez au moins deux caractères pour explorer les matières.');
  return <>
    <button className="header-search icon-button" ref={trigger} type="button" aria-label={copy.search} aria-haspopup="dialog" aria-expanded={open} onClick={() => { setOpen(true); dialog.current?.showModal(); }}><SiteIcon name="search" /></button>
    <dialog ref={dialog} className="search-dialog" aria-labelledby={`${id}-title`} onKeyDown={trapDialogFocus} onClose={reset} onClick={event => { if (event.target === event.currentTarget) dialog.current?.close(); }}>
      <div className="search-dialog-heading"><div><p className="eyebrow">LAAMACH MARBRE</p><h2 id={`${id}-title`}>{copy.search}</h2></div><button className="icon-button" type="button" aria-label={copy.close} onClick={() => dialog.current?.close()}><SiteIcon name="close" /></button></div>
      <form action={`/${locale}/products`} method="get" className="search-dialog-form" onSubmit={event => {
        if (composing) { event.preventDefault(); return; }
        if (active >= 0 && results[active]) { event.preventDefault(); (document.getElementById(`${id}-result-${active}`) as HTMLAnchorElement | null)?.click(); }
        else if (query.length < 2) event.preventDefault();
      }}>
        <SiteIcon name="search" /><label className="sr-only" htmlFor={`${id}-input`}>{copy.search}</label>
        <input id={`${id}-input`} autoFocus name="q" type="search" role="combobox" autoComplete="off" maxLength={120} value={value} placeholder={ar ? 'اسم الحجر، اللون…' : 'Une pierre, une nuance…'} aria-autocomplete="list" aria-expanded={results.length > 0} aria-controls={`${id}-results`} aria-activedescendant={active >= 0 ? `${id}-result-${active}` : undefined} aria-describedby={`${id}-status`}
          onCompositionStart={() => setComposing(true)} onCompositionEnd={() => setComposing(false)}
          onChange={event => { setValue(event.target.value); setResults([]); setActive(-1); setPhase(event.target.value.trim().length >= 2 ? 'waiting' : 'idle'); }}
          onKeyDown={event => {
            if (event.nativeEvent.isComposing) return;
            if (event.key === 'Escape') { event.preventDefault(); dialog.current?.close(); return; }
            if (!results.length) return;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); const step = event.key === 'ArrowDown' ? 1 : -1; setActive(current => current < 0 ? (step > 0 ? 0 : results.length - 1) : (current + step + results.length) % results.length); }
          }} />
        <button type="submit" className="icon-button" aria-label={copy.search}><SiteIcon name="arrow" className="directional" /></button>
      </form>
      <p className="search-status" id={`${id}-status`} role="status" aria-live="polite">{status}</p>
      <div id={`${id}-results`} role="listbox" aria-label={copy.products} className="search-suggestions" aria-busy={phase === 'loading'}>
        {results.map((product, index) => <a id={`${id}-result-${index}`} role="option" aria-selected={active === index} tabIndex={-1} key={product.reference} href={`/${locale}/products/${product.slug}`} onClick={() => dialog.current?.close()}>
          <CatalogueImage media={product.cover} alt={product.name} placeholder={catalogueCopy(locale).missingImage} thumbnail eager />
          <span><strong>{product.name}</strong><small>{product.category.name}</small></span><SiteIcon name="arrow" className="directional" />
        </a>)}
      </div>
      {phase === 'ready' && !results.length && <p className="search-empty">{ar ? 'جرّب اسماً آخر أو تصفح مجموعتنا كاملة.' : 'Essayez un autre nom ou parcourez toute la sélection.'}</p>}
      <a className="search-all text-link" href={`/${locale}/products${query.length >= 2 ? `?${new URLSearchParams({ q: query })}` : ''}`} onClick={() => dialog.current?.close()}>{ar ? 'تصفح المواد' : 'Explorer les matières'}<SiteIcon name="arrow" className="directional" /></a>
    </dialog>
  </>;
}
