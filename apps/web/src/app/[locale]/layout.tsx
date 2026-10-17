import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';

export default async function LocaleLayout({ children, params }: { children: React.ReactNode; params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!findLocale(locale)) notFound();
  return children;
}
