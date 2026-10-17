import { notFound } from 'next/navigation';
import { findLocale } from '@/lib/locales';
import { pageMetadata } from '@/lib/site-server';
import { EditorialPageView } from '@/components/editorial-page';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) { const { locale } = await params; if (!findLocale(locale)) return {}; return pageMetadata(locale, 'services', '/services'); }
export default async function Services({ params }: { params: Promise<{ locale: string }> }) { const { locale } = await params; if (!findLocale(locale)) notFound(); return <EditorialPageView locale={locale} kind="services" />; }
