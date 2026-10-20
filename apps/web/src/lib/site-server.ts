import 'server-only';
import { cache } from 'react';
import { headers } from 'next/headers';
import { catalogueRequest } from './catalogue-server';
import type { SiteData } from './site';
import { siteCopy } from './site';
import type { Metadata } from 'next';
import { editorialMedia } from './editorial-media';
import { seoMetadata } from './seo';

export const getSite = cache(async (locale: string): Promise<SiteData & { available: boolean }> => {
  const home = (await headers()).get('x-laamach-home') === 'true';
  const result = await catalogueRequest<{ data: SiteData }>('site', locale, new URLSearchParams(home ? { include_navigation: '1' } : {}));
  return result.data?.data ? { ...result.data.data, available: true } : { settings: null, pages: {}, featured_products: [], featured_projects: [], available: false };
});

export async function pageMetadata(locale: string, key: string, path: string): Promise<Metadata> {
  const site = await getSite(locale);
  const page = site.pages[key];
  const copy = siteCopy(locale);
  const fallback = key === 'home' ? 'LAAMACH MARBRE' : copy[key as 'products' | 'projects' | 'services' | 'about' | 'contact'];
  const title = page?.seo_title || page?.title || fallback;
  const description = page?.seo_description || page?.body || (key === 'products' ? copy.collectionIntro : key === 'projects' ? copy.projectIntro : key === 'contact' ? copy.contactIntro : copy.heroBody);
  const image = key === 'about' || key === 'services' ? editorialMedia(key, locale) : key === 'home' ? site.featured_projects[0]?.cover : page?.hero;
  const metadata = seoMetadata(locale, path, title, description, image, page?.available_locales || [locale]);
  // An unpublished editorial record can have a useful fallback page, but is not indexable.
  return page ? metadata : { ...metadata, robots: { index: false, follow: false } };
}
