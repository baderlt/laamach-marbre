import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';
import { getSite } from '@/lib/site-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { provisionalPreviewEnabled } from '@/lib/provisional-preview';
import { StoneBackground } from '@/components/stone-background';

export default async function LocaleLayout({ children, params }: { children: React.ReactNode; params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!findLocale(locale)) notFound();
  const site = await getSite(locale);
  return <div className="stone-site"><StoneBackground />{provisionalPreviewEnabled(process.env) && <aside className="provisional-notice" role="note">{locale === 'ar' ? 'معاينة للتصميم · المعلومات قيد المراجعة' : 'Maquette · informations à valider'}</aside>}<SiteHeader locale={locale} settings={site.settings} />{children}<SiteFooter locale={locale} settings={site.settings} /></div>;
}
