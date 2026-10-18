import type { Metadata } from 'next';
import { headers } from 'next/headers';
import { findLocale, localeConfiguration } from '@/lib/locales';
import './globals.css';
import './m52.css';
import './stone-direction.css';
import { serverEnvironment } from '@/lib/environment';
import localFont from 'next/font/local';

const cairo = localFont({ src: [{ path: '../fonts/cairo-400.ttf', weight: '400' }, { path: '../fonts/cairo-500.ttf', weight: '500' }, { path: '../fonts/cairo-600.ttf', weight: '600' }, { path: '../fonts/cairo-700.ttf', weight: '700' }], variable: '--font-arabic', display: 'swap', preload: false });

export const metadata: Metadata = { metadataBase: new URL(serverEnvironment(process.env).site), title: 'LAAMACH MARBRE', robots: { index: false, follow: false } };

export default async function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  const code = (await headers()).get('x-laamach-locale') || localeConfiguration.default;
  const locale = findLocale(code) || findLocale(localeConfiguration.default)!;
  return <html lang={locale.code} dir={locale.direction} className={cairo.variable}><body>{children}</body></html>;
}
