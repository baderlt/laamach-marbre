import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';
import { getSite, pageMetadata } from '@/lib/site-server';
import { siteCopy, phoneHref } from '@/lib/site';
import { PageHero, SectionHeading } from '@/components/page-design';
import { CatalogueImage } from '@/components/catalogue-image';
import { ContactForm } from '@/components/contact-form';
import { SiteIcon } from '@/components/site-icon';
import { ContactBanner } from '@/components/site-footer';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) { const { locale } = await params; if (!findLocale(locale)) return {}; return pageMetadata(locale, 'contact', '/contact'); }
export default async function Contact({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params; if (!findLocale(locale)) notFound();
  const copy = siteCopy(locale); const site = await getSite(locale); const page = site.pages.contact; const settings = site.settings;
  return <main id="main" className="public-page contact-page">
    <PageHero locale={locale} title={page?.title || copy.contactTitle} eyebrow={page?.eyebrow || copy.contact} body={page?.body || copy.contactIntro} media={page?.hero} large />
    <section className="section site-width"><SectionHeading title={copy.footerContact} /><div className="contact-cards"><article><SiteIcon name="phone" /><h2>{copy.phone}</h2>{settings?.phone ? <a href={phoneHref(settings.phone)}><bdi>{settings.phone}</bdi></a> : <p>{copy.notProvided}</p>}</article><article><SiteIcon name="mail" /><h2>{copy.email}</h2>{settings?.email ? <a href={`mailto:${settings.email}`}><bdi>{settings.email}</bdi></a> : <p>{copy.notProvided}</p>}</article><article><SiteIcon name="pin" /><h2>{copy.address}</h2><p className="preserve-text">{settings?.address || copy.notProvided}</p>{settings?.map_url && <a className="text-link" href={settings.map_url} target="_blank" rel="noopener noreferrer">{copy.map}</a>}</article><article><SiteIcon name="ruler" /><h2>{copy.preparation}</h2><p>{copy.preparationBody}</p><a className="text-link" href="#contact-form">{copy.message}</a></article></div></section>
    <section className="section site-width contact-composition"><div className="contact-image"><CatalogueImage media={page?.secondary || null} alt={copy.contactIntro} placeholder={copy.missingMedia} /><div><p className="eyebrow">LAAMACH MARBRE</p><h2>{copy.contactAction}</h2><p>{copy.contactIntro}</p></div></div><ContactForm locale={locale} email={settings?.email} /></section>
    <section className="section site-width location-section"><div className="location-placeholder" role="img" aria-label={copy.mapPending}><i /><i /><SiteIcon name="pin" /><span>{settings?.address || copy.mapPending}</span></div><div className="location-copy"><p className="eyebrow">{copy.address}</p><h2>{copy.footerContact}</h2><p className="preserve-text">{settings?.address || copy.mapPending}</p>{settings?.map_url && <a className="button outline" href={settings.map_url} target="_blank" rel="noopener noreferrer">{copy.map}<SiteIcon name="arrow" className="directional" /></a>}</div></section>
    <ContactBanner locale={locale} pageKey="contact" />
    {settings?.social_links.length ? <section className="section site-width contact-social"><SectionHeading title={copy.footerSocial} /><div className="social-links">{settings.social_links.map(link => <a key={link.url} href={link.url} target="_blank" rel="noopener noreferrer">{link.label}</a>)}</div></section> : null}
  </main>;
}
