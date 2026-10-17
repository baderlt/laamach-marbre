import 'server-only';
import { cache } from 'react';
import { catalogueRequest } from './catalogue-server';
import type { SiteData } from './site';
import { siteCopy } from './site';
import { serverEnvironment } from './environment';
import type { Metadata } from 'next';

export const getSite = cache(async (locale: string): Promise<SiteData> => {
  const result = await catalogueRequest<{ data: SiteData }>('site', locale);
  return result.data?.data || { settings: null, pages: {}, featured_products: [], featured_projects: [] };
});

export async function pageMetadata(locale: string, key: string, path: string): Promise<Metadata> {
  const site = await getSite(locale);
  const page = site.pages[key];
  const copy = siteCopy(locale);
  const fallback = key === 'home' ? 'LAAMACH MARBRE' : copy[key as 'products' | 'projects' | 'services' | 'about' | 'contact'];
  return { title: page?.seo_title || page?.title || fallback, description: page?.seo_description || undefined, alternates: { canonical: `${serverEnvironment(process.env).site}/${locale}${path}` } };
}
