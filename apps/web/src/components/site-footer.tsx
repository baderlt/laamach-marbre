import { siteCopy, phoneHref, type SiteSettings } from '@/lib/site';
import { SiteIcon } from './site-icon';
import { getSite } from '@/lib/site-server';
import { CatalogueImage } from './catalogue-image';
import { provisionalPreviewEnabled } from '@/lib/provisional-preview';

export async function ContactBanner({ locale, pageKey = 'home' }: { locale: string; pageKey?: string }) {
  const copy = siteCopy(locale);
  const site = await getSite(locale); const page = site.pages[pageKey] || site.pages.home;
  const media = provisionalPreviewEnabled(process.env) ? null : page?.secondary || page?.hero;
  return <section data-stone-surface={!media ? '' : undefined} className={`contact-banner banner-${pageKey}${!media ? ' stone-surface' : ''}`}>{media && <div className="banner-media" aria-hidden="true"><CatalogueImage media={media} placeholder="" /></div>}<div className="site-width contact-banner-inner"><div><p className="eyebrow">LAAMACH MARBRE</p><h2>{page?.cta_title || copy.ctaTitle}</h2><p>{page?.cta_body || copy.ctaBody}</p></div><div className="banner-actions"><a className="button bronze" href={`/${locale}/contact`}>{copy.contactAction}<SiteIcon name="arrow" className="directional" /></a><a className="button outline" href={`/${locale}/contact#contact-form`}>{copy.preparation}<SiteIcon name="phone" /></a></div></div></section>;
}

export function SiteFooter({ locale, settings }: { locale: string; settings: SiteSettings | null }) {
  const copy = siteCopy(locale);
  return <footer className="site-footer"><div className="site-width footer-grid"><div className="footer-brand"><a className="wordmark" href={`/${locale}`}><SiteIcon name="brand" /><span className="wordmark-copy"><b lang="fr" dir="ltr">LAAMACH <strong>MARBRE</strong></b><small>{settings?.tagline || copy.footerIntro}</small></span></a>{settings?.footer_text && <p className="preserve-text">{settings.footer_text}</p>}</div><div><h2>{copy.footerNav}</h2><div className="footer-links">{(['home', 'products', 'projects', 'services', 'about', 'contact'] as const).map(key => <a key={key} href={`/${locale}${key === 'home' ? '' : `/${key}`}`}>{copy[key]}</a>)}</div></div><div><h2>{copy.products}</h2><div className="footer-links"><a href={`/${locale}/products`}>{copy.productsAction}</a><a href={`/${locale}/services`}>{copy.footerFormats}</a><a href={`/${locale}/services`}>{copy.footerFinishes}</a><a href={`/${locale}/contact`}>{copy.preparation}</a></div></div><div><h2>{copy.footerContact}</h2><div className="footer-contacts">{settings?.phone && <a href={phoneHref(settings.phone)}><SiteIcon name="phone" /><bdi>{settings.phone}</bdi></a>}{settings?.email && <a href={`mailto:${settings.email}`}><SiteIcon name="mail" /><bdi>{settings.email}</bdi></a>}{settings?.address && <p><SiteIcon name="pin" />{settings.address}</p>}{!settings?.phone && !settings?.email && !settings?.address && <p className="muted">{copy.notProvided}</p>}</div></div><div><h2>{copy.footerSocial}</h2>{settings?.social_links.length ? <div className="social-links">{settings.social_links.map(link => <a key={link.url} href={link.url} target="_blank" rel="noopener noreferrer">{link.label}</a>)}</div> : <p className="muted">{copy.pendingValidation}</p>}</div></div><div className="site-width footer-bottom"><span>© {new Date().getFullYear()} {copy.copyright}</span><a href="#top" aria-label={copy.backTop}>↑</a></div></footer>;
}
