'use client';

import { trapDialogFocus } from '@/lib/dialog-focus';

import { useEffect, useRef, useState } from 'react';
import { usePathname, useSearchParams } from 'next/navigation';
import { localeConfiguration } from '@/lib/locales';
import { siteCopy, phoneHref, type SiteSettings } from '@/lib/site';
import { CatalogueImage } from './catalogue-image';
import { SiteIcon } from './site-icon';

export function SiteHeader({ locale, settings }: { locale: string; settings: SiteSettings | null }) {
  const copy = siteCopy(locale);
  const pathname = usePathname();
  const [scrolled, setScrolled] = useState(false);
  useEffect(() => {
    const update = () => setScrolled(window.scrollY > 64);
    update(); window.addEventListener('scroll', update, { passive: true });
    return () => window.removeEventListener('scroll', update);
  }, []);
  const query = useSearchParams().toString();
  const menu = useRef<HTMLDialogElement>(null);
  const links = [['', copy.home], ['products', copy.products], ['projects', copy.projects], ['services', copy.services], ['about', copy.about], ['contact', copy.contact]];
  const navigation = links.map(([path, label]) => <a key={path} href={`/${locale}${path ? `/${path}` : ''}`} aria-current={pathname === `/${locale}${path ? `/${path}` : ''}` ? 'page' : undefined} onClick={() => menu.current?.close()}>{label}</a>);
  return <>
    <a href="#main" className="skip-link">{copy.skip}</a>
    <header className="site-header" id="top" data-home={pathname === `/${locale}`} data-scrolled={scrolled}>
      <div className="top-strip"><div className="site-width top-strip-inner"><div className="top-contacts">{settings?.phone && <a href={phoneHref(settings.phone)}><SiteIcon name="phone" /><bdi>{settings.phone}</bdi></a>}{settings?.email && <a href={`mailto:${settings.email}`}><SiteIcon name="mail" /><bdi>{settings.email}</bdi></a>}{settings?.address && <span><SiteIcon name="pin" />{settings.address}</span>}</div><nav className="language-nav" aria-label={copy.language}>{localeConfiguration.locales.map(item => <a key={item.code} href={`${pathname.replace(`/${locale}`, `/${item.code}`)}${query ? `?${query}` : ''}`} lang={item.code} dir={item.direction} aria-current={item.code === locale ? 'page' : undefined}>{item.name}</a>)}</nav></div></div>
      <div className="site-width header-main"><a className="wordmark" href={`/${locale}`} aria-label="LAAMACH MARBRE">{settings?.logo ? <CatalogueImage media={settings.logo} placeholder="LAAMACH MARBRE" eager /> : <><SiteIcon name="brand" /><span className="wordmark-copy"><b lang="fr" dir="ltr">LAAMACH <strong>MARBRE</strong></b><small>{settings?.tagline || copy.footerIntro}</small></span></>}</a><a className="header-search" href={`/${locale}/products#search`} aria-label={copy.search}><SiteIcon name="search" /></a><nav className="desktop-nav" aria-label={copy.menu}>{navigation}</nav><a className="button bronze header-cta" href={`/${locale}/contact`}>{copy.contactAction}<SiteIcon name="arrow" className="directional" /></a><button className="menu-toggle icon-button" type="button" onClick={() => menu.current?.showModal()} aria-label={copy.menu}><SiteIcon name="menu" /></button></div>
    </header>
    <dialog onKeyDown={trapDialogFocus} ref={menu} className="mobile-menu" aria-label={copy.menu} onClick={event => { if (event.target === event.currentTarget) menu.current?.close(); }}><div className="drawer-heading"><b>LAAMACH MARBRE</b><button type="button" className="icon-button" onClick={() => menu.current?.close()} aria-label={copy.close}><SiteIcon name="close" /></button></div><nav aria-label={copy.menu}>{navigation}</nav><a className="button bronze" href={`/${locale}/contact`} onClick={() => menu.current?.close()}>{copy.contactAction}</a></dialog>
  </>;
}
