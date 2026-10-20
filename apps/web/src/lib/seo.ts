import type { Metadata } from 'next';
import type { Media } from './catalogue.ts';
import { localeConfiguration } from './locales.ts';
import { serverEnvironment } from './environment.ts';

export function descriptionText(value?: string | null): string | undefined {
  const text = value?.replace(/<[^>]*>/g, ' ').replace(/[\u0000-\u001f\u007f\u202a-\u202e\u2066-\u2069]/g, '').replace(/\s+/g, ' ').trim();
  return text ? Array.from(text).slice(0, 180).join('') : undefined;
}

export function seoMetadata(locale: string, path: string, title: string, description: string | null | undefined, media: Media | null | undefined, available: string[] = [locale]): Metadata {
  const environment = serverEnvironment(process.env);
  const url = `${environment.site}/${locale}${path}`;
  const text = descriptionText(description);
  const variant = media?.variants.filter(item => item.format === 'webp').sort((a, b) => Math.abs(a.width - 1200) - Math.abs(b.width - 1200)).find(item => {
    try { const parsed = new URL(item.url, environment.site); return !parsed.username && !parsed.password && [environment.site, ...(environment.stage === 'local' ? [environment.backend] : []), ...environment.mediaOrigins].includes(parsed.origin) && !/\/(private|scratch|originals?)\//i.test(parsed.pathname); } catch { return false; }
  });
  const images = variant ? [{ url: new URL(variant.url, environment.site).href, width: variant.width, height: variant.height, alt: media?.alt || title }] : [];
  return {
    title: { absolute: title.includes('LAAMACH MARBRE') ? title : `${title} | LAAMACH MARBRE` }, description: text,
    robots: { index: environment.indexing, follow: environment.indexing },
    alternates: { canonical: url, languages: Object.fromEntries(localeConfiguration.locales.filter(item => available.includes(item.code)).map(item => [item.code, `${environment.site}/${item.code}${path}`])) },
    openGraph: { title, description: text, url, type: 'website', locale: locale === 'ar' ? 'ar_MA' : 'fr_MA', siteName: 'LAAMACH MARBRE', images },
    twitter: { card: images.length ? 'summary_large_image' : 'summary', title, description: text, images: images.map(item => item.url) },
  };
}

// Values are data, never administrator-supplied executable/schema fragments.
export function jsonLd(value: unknown): string {
  return JSON.stringify(value).replace(/</g, '\\u003c').replace(/\u2028/g, '\\u2028').replace(/\u2029/g, '\\u2029');
}

export function breadcrumbs(items: { name: string; url: string }[]) {
  return { '@context': 'https://schema.org', '@type': 'BreadcrumbList', itemListElement: items.map((item, index) => ({ '@type': 'ListItem', position: index + 1, name: item.name, item: item.url })) };
}
