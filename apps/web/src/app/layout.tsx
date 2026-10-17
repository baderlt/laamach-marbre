import type { Metadata } from 'next';
import { headers } from 'next/headers';
import { findLocale, localeConfiguration } from '@/lib/locales';
import './globals.css';

export const metadata: Metadata = { title: 'LAAMACH MARBRE', robots: { index: false, follow: false } };

export default async function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  const code = (await headers()).get('x-laamach-locale') || localeConfiguration.default;
  const locale = findLocale(code) || findLocale(localeConfiguration.default)!;
  return <html lang={locale.code} dir={locale.direction}><body>{children}</body></html>;
}
