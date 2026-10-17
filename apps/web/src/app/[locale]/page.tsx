import { notFound } from 'next/navigation';
import { backendAvailable } from '@/lib/backend';
import { findLocale, localeConfiguration } from '@/lib/locales';
import { getMessages } from '@/lib/messages';

export default async function FoundationPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!findLocale(locale)) notFound();
  const copy = getMessages(locale);
  const available = await backendAvailable();
  return <main>
    <p className="text-sm font-semibold tracking-wide" dir="ltr">LAAMACH MARBRE</p>
    <h1 className="text-3xl font-semibold">{copy.title}</h1>
    <p>{copy.description}</p>
    <nav aria-label={copy.language}>{localeConfiguration.locales.map(item => <a key={item.code} href={`/${item.code}`} lang={item.code} dir={item.direction} aria-current={locale === item.code ? 'page' : undefined}>{item.name}</a>)}</nav>
    <p data-backend-status={available ? 'ok' : 'unavailable'}>{copy.backend} : {available ? copy.online : copy.unavailable}</p>
  </main>;
}
