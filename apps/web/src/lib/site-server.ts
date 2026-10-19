import 'server-only';
import { cache } from 'react';
import { catalogueRequest } from './catalogue-server';
import type { SiteData } from './site';
import { siteCopy } from './site';
import { serverEnvironment } from './environment';
import type { Metadata } from 'next';
import { localeConfiguration } from './locales';
import { editorialMedia } from './editorial-media';

export const getSite = cache(async (locale: string): Promise<SiteData> => {
  const result = await catalogueRequest<{ data: SiteData }>('site', locale);
  return result.data?.data || { settings: null, pages: {}, featured_products: [], featured_projects: [] };
});

export async function pageMetadata(locale: string, key: string, path: string): Promise<Metadata> {
  const site = await getSite(locale);
  const page = site.pages[key];
  const copy = siteCopy(locale);
  const fallback = key === 'home' ? 'LAAMACH MARBRE' : copy[key as 'products' | 'projects' | 'services' | 'about' | 'contact'];
  const title = page?.seo_title || page?.title || fallback;
  const description = page?.seo_description || undefined;
  const image = (key === 'about' || key === 'services' ? editorialMedia(key, locale) : key === 'home' ? site.featured_projects[0]?.cover : page?.hero)?.variants.find(variant => variant.format === 'webp');
  const url = `${serverEnvironment(process.env).site}/${locale}${path}`;
  return { title, description, alternates: { canonical: url, languages: Object.fromEntries(localeConfiguration.locales.map(item => [item.code, `${serverEnvironment(process.env).site}/${item.code}${path}`])) }, openGraph: { title, description, url, type: 'website', images: image ? [{ url: image.url, width: image.width, height: image.height }] : [] }, twitter: { card: 'summary_large_image', title, description, images: image ? [image.url] : [] } };
}
