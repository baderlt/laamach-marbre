'use client';

import { trapDialogFocus } from '@/lib/dialog-focus';

import { useEffect, useRef, useState } from 'react';
import { usePathname, useSearchParams } from 'next/navigation';
import { localeConfiguration } from '@/lib/locales';
import { siteCopy, phoneHref, type SiteSettings } from '@/lib/site';
import { SiteIcon } from './site-icon';
import { HeaderSearch } from './header-search';

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
  const drawerIcons: Record<string, string> = { '': 'home', products: 'stone', projects: 'building', services: 'craft', about: 'people', contact: 'mail' };
  const drawerNavigation = links.map(([path, label]) => <a key={path} href={`/${locale}${path ? `/${path}` : ''}`} aria-current={pathname === `/${locale}${path ? `/${path}` : ''}` || (path && pathname.startsWith(`/${locale}/${path}/`)) ? 'page' : undefined} onClick={() => menu.current?.close()}><span className="drawer-nav-icon"><SiteIcon name={drawerIcons[path]} /></span><span>{label}</span><SiteIcon name="arrow" className="directional" /></a>);
  return <>
    <a href="#main" className="skip-link">{copy.skip}</a>
    <header className="site-header" id="top" data-home={pathname === `/${locale}`} data-scrolled={scrolled}>
      <div className="service-announcement"><div className="site-width announcement-inner"><span className="announcement-delivery"><SiteIcon name="truck" /><span>{locale === 'ar' ? 'حجر مغربي · التوصيل إلى جميع أنحاء المغرب' : 'Pierre marocaine · Livraison partout au Maroc'}</span></span><a href={`/${locale}/contact`}>{locale === 'ar' ? 'فكرة مشروعك تبدأ هنا' : 'Votre projet commence ici'}<SiteIcon name="arrow" className="directional" /></a></div></div>
      <div className="top-strip"><div className="site-width top-strip-inner"><div className="top-contacts">{settings?.phone && <a href={phoneHref(settings.phone)}><SiteIcon name="phone" /><bdi>{settings.phone}</bdi></a>}{settings?.email && <a href={`mailto:${settings.email}`}><SiteIcon name="mail" /><bdi>{settings.email}</bdi></a>}{settings?.address && <span><SiteIcon name="pin" />{settings.address}</span>}</div></div></div>
      <div className="site-width header-main"><a className="wordmark" href={`/${locale}`} aria-label="LAAMACH MARBRE"><img className="client-logo" src="/brand/laamach-final-logo.webp" width="640" height="480" alt="Laamach marbre et construction" /></a><HeaderSearch locale={locale} /><nav className="desktop-nav" aria-label={copy.menu}>{navigation}</nav><a className="button bronze header-cta" href={`/${locale}/contact`}>{copy.contactAction}<SiteIcon name="arrow" className="directional" /></a><nav className="header-language-switch" aria-label={copy.language}>{localeConfiguration.locales.map(item => <a key={item.code} href={`${pathname.replace(`/${locale}`, `/${item.code}`)}${query ? `?${query}` : ''}`} lang={item.code} dir={item.direction} aria-label={item.name} title={item.name} aria-current={item.code === locale ? 'page' : undefined}>{item.code === 'ar' ? 'العربية' : item.code.toUpperCase()}</a>)}</nav><button className="menu-toggle icon-button" type="button" onClick={() => menu.current?.showModal()} aria-label={copy.menu}><SiteIcon name="menu" /></button></div>
    </header>
    <dialog onKeyDown={trapDialogFocus} ref={menu} className="mobile-menu" aria-label={copy.menu} onClick={event => { if (event.target === event.currentTarget) menu.current?.close(); }}>
      <div className="drawer-heading"><a href={`/${locale}`} aria-label="LAAMACH MARBRE"><img src="/brand/laamach-final-logo.webp" width="160" height="120" alt="Laamach marbre" /></a><button type="button" className="icon-button" onClick={() => menu.current?.close()} aria-label={copy.close}><SiteIcon name="close" /></button></div>
      <div className="drawer-content"><p className="drawer-label">{locale === 'ar' ? 'اكتشف عالم الحجر' : 'Explorez notre univers'}</p><nav className="drawer-menu-nav" aria-label={copy.menu}>{drawerNavigation}</nav>
      <div className="drawer-language-panel"><span className="drawer-label"><SiteIcon name="globe" />{copy.language}</span><nav className="drawer-languages" aria-label={copy.language}>{localeConfiguration.locales.map(item => <a key={item.code} href={`${pathname.replace(`/${locale}`, `/${item.code}`)}${query ? `?${query}` : ''}`} lang={item.code} dir={item.direction} aria-current={item.code === locale ? 'page' : undefined}><span className="drawer-locale-code">{item.code.toUpperCase()}</span>{item.name}{item.code === locale && <SiteIcon name="tick" />}</a>)}</nav></div>
      <a className="button bronze drawer-quote" href={`/${locale}/contact`} onClick={() => menu.current?.close()}>{copy.contactAction}<SiteIcon name="arrow" className="directional" /></a></div>
    </dialog>
  </>;
}
