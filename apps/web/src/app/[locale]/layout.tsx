import { StructuredData } from '@/components/structured-data';
import { serverEnvironment } from '@/lib/environment';
import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';
import { getSite } from '@/lib/site-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { StoneBackground } from '@/components/stone-background';
import { FloatingWhatsApp } from '@/components/floating-whatsapp';

export default async function LocaleLayout({ children, params }: { children: React.ReactNode; params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!findLocale(locale)) notFound();
  const site = await getSite(locale);
  const origin = serverEnvironment(process.env).site;
  return <div className="stone-site"><StructuredData value={{ '@context': 'https://schema.org', '@type': 'Organization', '@id': `${origin}/#organization`, name: 'LAAMACH MARBRE', url: origin, logo: `${origin}/brand/laamach-final-logo.webp`, ...(site.settings?.phone ? { telephone: site.settings.phone } : {}), ...(site.settings?.email ? { email: site.settings.email } : {}) }} /><StoneBackground /><SiteHeader locale={locale} settings={site.settings} />{children}<SiteFooter locale={locale} settings={site.settings} /><FloatingWhatsApp locale={locale} settings={site.settings} /></div>;
}
